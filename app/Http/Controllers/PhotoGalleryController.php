<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoGroup;
use App\Models\PhotoPerson;
use App\Models\PhotoPrivacyRequest;
use App\Services\Photos\PhotoGalleryAccess;
use App\Services\Photos\PhotoOrderService;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fotos do espetáculo à venda: lista de galerias e a galeria de um evento.
 *
 * A galeria mostra rosto e nome de crianças, então só abre para quem tem
 * ingresso pago do evento ou o código de acesso (ver PhotoGalleryAccess).
 */
class PhotoGalleryController extends Controller
{
    /** Códigos errados aceitos por galeria em uma hora, somando todos os IPs. */
    private const MAX_CODE_FAILURES_PER_HOUR = 50;

    public function __construct(private readonly PhotoGalleryAccess $access) {}

    public function index(Request $request): Response
    {
        $galleries = PhotoGallery::with(['event.venue', 'event.sessions', 'event.sectors'])
            ->withCount(['photos', 'people'])
            ->where('status', 'published')
            ->latest('published_at')
            ->get()
            ->map(function (PhotoGallery $g) use ($request): array {
                $unlocked = $this->access->allows($g, $request);

                return [
                    'event' => CatalogPresenter::listItem($g->event),
                    'photoCount' => $g->photos_count,
                    'peopleCount' => $g->people_count,
                    'locked' => ! $unlocked,
                    // Sem acesso a capa é o banner do evento: prévia de foto expõe criança.
                    'covers' => $unlocked
                        ? $g->photos()->limit(3)->pluck('id')->map(fn ($id) => self::photoUrl($id, 'thumb'))->all()
                        : [],
                    'tiers' => self::tiers($g),
                ];
            });

        // Antes do espetáculo não existe "Fotos" no site: sem galeria publicada,
        // a página some para o público (o painel continua vendo, para conferir).
        abort_if($galleries->isEmpty() && $request->user('painel') === null, 404);

        return Inertia::render('buyer/photos', ['galleries' => $galleries]);
    }

    public function show(Request $request, string $slug, PhotoOrderService $photoOrders): Response
    {
        $user = $request->user('web');
        $gallery = $this->published($slug);

        if (! $this->access->allows($gallery, $request)) {
            return $this->locked($request, $gallery);
        }

        $photos = $gallery->photos()->with('people:id')->get();

        $counts = [];
        foreach ($photos as $photo) {
            foreach ($photo->people as $person) {
                $counts[$person->id] = ($counts[$person->id] ?? 0) + 1;
            }
        }
        $groupCounts = $photos->countBy('photo_group_id');

        return Inertia::render('buyer/photo-gallery', [
            'gallery' => [
                'id' => $gallery->id,
                'description' => $gallery->description,
                'event' => CatalogPresenter::listItem($gallery->event),
                'tiers' => self::tiers($gallery),
            ],
            'groups' => $gallery->groups
                ->map(fn (PhotoGroup $g): array => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'count' => $groupCounts[$g->id] ?? 0,
                ])
                ->filter(fn (array $g): bool => $g['count'] > 0)
                ->values(),
            'people' => $gallery->people
                ->reject(fn (PhotoPerson $p): bool => (bool) $p->hidden)
                ->map(fn (PhotoPerson $p): array => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'face' => $p->face_path ? route('photos.face', $p, false) : null,
                    'count' => $counts[$p->id] ?? 0,
                ])
                ->filter(fn (array $p): bool => $p['count'] > 0)
                ->values(),
            'photos' => $photos->map(fn (Photo $p): array => [
                'id' => $p->id,
                'thumb' => self::photoUrl($p->id, 'thumb'),
                'preview' => self::photoUrl($p->id, 'preview'),
                'width' => $p->width,
                'height' => $p->height,
                'group' => $p->photo_group_id,
                'people' => $p->people->pluck('id'),
            ]),
            'purchaseUrl' => route('photos.checkout.start', $slug),
            'emailCodeUrl' => route('email-code.send'),
            'ownedIds' => $user !== null
                ? $photoOrders->ownedPhotoIds($user, $photos->pluck('id')->all())
                : [],
        ]);
    }

    /** Confere o código digitado e libera a galeria no navegador (e na conta, se logado). */
    public function unlock(Request $request, string $slug): RedirectResponse
    {
        $gallery = $this->published($slug);
        $data = $request->validate(['code' => ['required', 'string', 'max:40']], [
            'code.required' => 'Digite o código que a escola enviou.',
        ]);

        // Trava por galeria, independente de IP: o limite da rota é por pessoa e
        // por IP, e quem troca de IP poderia continuar chutando.
        $failures = 'photo-code-failures:'.$gallery->id;

        if (RateLimiter::tooManyAttempts($failures, self::MAX_CODE_FAILURES_PER_HOUR)) {
            return back()->withErrors(['code' => 'Muitas tentativas nesta galeria. Tente de novo daqui a pouco ou peça ajuda à escola.']);
        }

        if (! $gallery->acceptsCode($data['code'])) {
            RateLimiter::hit($failures, 3600);

            if (RateLimiter::attempts($failures) === self::MAX_CODE_FAILURES_PER_HOUR) {
                Log::warning('Galeria de fotos travada por excesso de códigos errados', ['gallery_id' => $gallery->id]);
            }

            return back()->withErrors(['code' => 'Esse código não confere. Confira com a escola e tente de novo.']);
        }

        $this->access->unlock($gallery, $request);

        return redirect()->route('photos.show', $slug);
    }

    /** Pedido para tirar a criança do filtro por rosto. O painel trata depois. */
    public function privacyRequest(Request $request, string $slug): RedirectResponse
    {
        $gallery = $this->published($slug);
        abort_unless($this->access->allows($gallery, $request), 404);

        $data = $request->validate([
            'guardian_name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:160'],
            'child_name' => ['required', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'guardian_name.required' => 'Diga seu nome.',
            'contact.required' => 'Deixe um e-mail ou WhatsApp para a gente responder.',
            'child_name.required' => 'Diga o nome da criança.',
        ]);

        PhotoPrivacyRequest::create($data + [
            'photo_gallery_id' => $gallery->id,
            'user_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Pedido enviado. A criança sai do filtro por rosto e a gente confirma com você.');
    }

    private function published(string $slug): PhotoGallery
    {
        return PhotoGallery::with(['event.venue', 'event.sessions', 'event.sectors', 'groups', 'people'])
            ->whereHas('event', fn ($q) => $q->where('slug', $slug))
            ->where('status', 'published')
            ->firstOrFail();
    }

    /** Tela de entrada: capa do evento, campo do código e atalho para entrar com a conta do ingresso. */
    private function locked(Request $request, PhotoGallery $gallery): Response
    {
        return Inertia::render('buyer/photo-gallery-locked', [
            'event' => CatalogPresenter::listItem($gallery->event),
            'photoCount' => $gallery->photos()->count(),
            'loginUrl' => '/login?redirect='.urlencode('/fotos/'.$gallery->event->slug),
            'account' => $request->user()?->email,
        ]);
    }

    /** @return array<int, array{minQty: int, unitPrice: float}> */
    private static function tiers(PhotoGallery $gallery): array
    {
        return array_map(fn (array $t): array => [
            'minQty' => (int) $t['min_qty'],
            'unitPrice' => Money::toReais((int) $t['unit_price_cents']),
        ], $gallery->sortedTiers());
    }

    /** Caminho relativo: funciona em qualquer porta/host sem depender do APP_URL. */
    private static function photoUrl(int $id, string $variant): string
    {
        return route('photos.image', ['photo' => $id, 'variant' => $variant], false);
    }
}
