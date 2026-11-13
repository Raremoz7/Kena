<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Support\Money;
use App\Support\PhotoPriceTiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel das fotos do espetáculo: uma galeria por evento (ajustes e publicação),
 * as pessoas identificadas e a conferência das fotos importadas.
 */
class PhotoGalleryController extends Controller
{
    private const PHOTOS_PER_PAGE = 48;

    /** Faixas sugeridas ao criar uma galeria nova. */
    private const DEFAULT_TIERS = [
        ['min_qty' => 1, 'unit_price_cents' => 3500],
        ['min_qty' => 5, 'unit_price_cents' => 2500],
        ['min_qty' => 10, 'unit_price_cents' => 2000],
    ];

    public function index(): Response
    {
        $galleries = PhotoGallery::withCount(['photos', 'people', 'groups'])
            ->get()
            ->keyBy('event_id');

        $rows = Event::orderByDesc('id')->get(['id', 'title', 'slug'])
            ->map(function (Event $event) use ($galleries): array {
                $gallery = $galleries->get($event->id);

                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'hasGallery' => $gallery !== null,
                    'status' => $gallery?->status,
                    'photoCount' => $gallery?->photos_count ?? 0,
                    'peopleCount' => $gallery?->people_count ?? 0,
                    'groupCount' => $gallery?->groups_count ?? 0,
                    'fromPrice' => $gallery ? $this->fromPrice($gallery) : null,
                    // Pagamento ainda não existe nesta branch: vendas entram quando ele chegar.
                    'sales' => null,
                    'publicUrl' => $gallery?->status === 'published' ? '/fotos/'.$event->slug : null,
                ];
            })
            // Quem já tem galeria vem primeiro.
            ->sortBy(fn (array $r): int => $r['hasGallery'] ? 0 : 1)
            ->values();

        return Inertia::render('admin/photos', ['events' => $rows]);
    }

    public function edit(Event $event): Response
    {
        $gallery = $event->photoGallery;

        return Inertia::render('admin/photo-gallery-form', [
            'shell' => $this->shell($event, $gallery),
            'form' => [
                'description' => $gallery?->description ?? '',
                'tiers' => array_map(fn (array $t): array => [
                    'min_qty' => (int) $t['min_qty'],
                    'price' => Money::toReais((int) $t['unit_price_cents']),
                ], $gallery ? $gallery->sortedTiers() : self::DEFAULT_TIERS),
            ],
        ]);
    }

    public function save(Request $request, Event $event): RedirectResponse
    {
        // Aceita "35,50" como "35.50": o organizador digita em reais, com vírgula.
        if (is_array($request->input('tiers'))) {
            $request->merge(['tiers' => array_map(
                fn ($t) => is_array($t) && isset($t['price']) && is_string($t['price'])
                    ? [...$t, 'price' => str_replace(',', '.', trim($t['price']))]
                    : $t,
                $request->input('tiers'),
            )]);
        }

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:1000'],
            'tiers' => ['required', 'array', 'min:1', 'max:'.PhotoPriceTiers::MAX_TIERS],
            'tiers.*.min_qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'tiers.*.price' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ], [
            'tiers.required' => 'Informe pelo menos uma faixa de preço.',
            'tiers.*.min_qty.required' => 'Informe a quantidade mínima de cada faixa.',
            'tiers.*.min_qty.integer' => 'A quantidade mínima precisa ser um número inteiro.',
            'tiers.*.min_qty.min' => 'A quantidade mínima precisa ser pelo menos 1.',
            'tiers.*.price.required' => 'Informe o preço de cada faixa.',
            'tiers.*.price.numeric' => 'O preço de cada faixa precisa ser um valor em reais.',
            'tiers.*.price.min' => 'O preço de cada faixa precisa ser maior que zero.',
        ]);

        $tiers = PhotoPriceTiers::normalize(array_map(fn (array $t): array => [
            'min_qty' => $t['min_qty'],
            'unit_price_cents' => (int) round(((float) $t['price']) * 100),
        ], $data['tiers']));

        $errors = PhotoPriceTiers::errors($tiers);
        if ($errors !== []) {
            throw ValidationException::withMessages(['tiers' => $errors]);
        }

        PhotoGallery::updateOrCreate(
            ['event_id' => $event->id],
            [
                'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
                'price_tiers' => $tiers,
            ],
        );

        return redirect()->route('admin.photos.edit', $event)->with('status', 'Galeria salva.');
    }

    public function publish(Event $event): RedirectResponse
    {
        $gallery = $this->galleryOf($event);

        if (! $gallery->photos()->exists()) {
            throw ValidationException::withMessages([
                'publish' => 'Importe as fotos antes de publicar: a galeria está vazia.',
            ]);
        }

        $gallery->update([
            'status' => 'published',
            'published_at' => $gallery->published_at ?? now(),
        ]);

        return back()->with('status', 'Galeria publicada em /fotos.');
    }

    public function unpublish(Event $event): RedirectResponse
    {
        $this->galleryOf($event)->update(['status' => 'draft']);

        return back()->with('status', 'Galeria despublicada.');
    }

    public function people(Event $event): Response
    {
        $gallery = $this->galleryOf($event);

        $people = $gallery->people()->withCount('photos')->get()
            ->map(fn (PhotoPerson $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'face' => $p->face_path ? route('photos.face', $p, false) : null,
                'photoCount' => $p->photos_count,
                'hidden' => (bool) $p->hidden,
            ])->values();

        return Inertia::render('admin/photo-people', [
            'shell' => $this->shell($event, $gallery),
            'people' => $people,
        ]);
    }

    public function renamePerson(Request $request, PhotoPerson $person): RedirectResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('photo_people', 'name')
                    ->where('photo_gallery_id', $person->photo_gallery_id)
                    ->ignore($person->id),
            ],
        ], [
            'name.unique' => 'Já existe uma pessoa com esse nome. Use "Unir com outra pessoa" para juntar as duas.',
        ]);

        $person->update(['name' => trim($data['name'])]);

        return back()->with('status', 'Nome atualizado.');
    }

    /** Junta esta pessoa a outra da mesma galeria: as fotos vão para a escolhida e esta some. */
    public function mergePerson(Request $request, PhotoPerson $person): RedirectResponse
    {
        $data = $request->validate([
            'into' => [
                'required', 'integer',
                Rule::exists('photo_people', 'id')->where('photo_gallery_id', $person->photo_gallery_id),
            ],
        ]);

        abort_if((int) $data['into'] === $person->id, 422);

        $target = PhotoPerson::findOrFail($data['into']);

        DB::transaction(function () use ($person, $target): void {
            $already = DB::table('photo_person')->where('photo_person_id', $target->id)->pluck('photo_id');

            // Fotos em que as duas aparecem ficam só com a pessoa de destino.
            DB::table('photo_person')
                ->where('photo_person_id', $person->id)
                ->whereNotIn('photo_id', $already)
                ->update(['photo_person_id' => $target->id]);

            $updates = [];
            if ($target->face_path === null && $person->face_path !== null) {
                $updates['face_path'] = $person->face_path;
            }
            // Se qualquer uma das duas estava escondida, a união continua escondida.
            if ($person->hidden && ! $target->hidden) {
                $updates['hidden'] = true;
            }
            if ($updates !== []) {
                $target->update($updates);
            }

            $person->delete();
        });

        return back()->with('status', 'Pessoas unidas em "'.$target->name.'".');
    }

    public function togglePerson(Request $request, PhotoPerson $person): RedirectResponse
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);

        $person->update(['hidden' => $data['hidden']]);

        return back()->with('status', $data['hidden'] ? 'Pessoa escondida do filtro por rosto.' : 'Pessoa de volta ao filtro por rosto.');
    }

    public function photos(Request $request, Event $event): Response
    {
        $gallery = $this->galleryOf($event);

        $groups = $gallery->groups()->get(['id', 'name']);
        $groupCounts = $gallery->photos()->reorder()->selectRaw('photo_group_id, count(*) as total')
            ->groupBy('photo_group_id')->pluck('total', 'photo_group_id');

        $filter = (string) $request->query('turma', '');

        $query = $gallery->photos()->with(['group:id,name', 'people:id,name']);
        if ($filter === 'sem') {
            $query->whereNull('photo_group_id');
        } elseif (ctype_digit($filter)) {
            $query->where('photo_group_id', (int) $filter);
        } else {
            $filter = '';
        }

        $photos = $query->paginate(self::PHOTOS_PER_PAGE)->withQueryString()
            ->through(fn (Photo $p): array => [
                'id' => $p->id,
                'filename' => $p->filename,
                'thumb' => route('photos.image', ['photo' => $p->id, 'variant' => 'thumb'], false),
                'group' => $p->group?->name,
                'people' => $p->people->pluck('name')->values(),
            ]);

        return Inertia::render('admin/photo-photos', [
            'shell' => $this->shell($event, $gallery),
            'photos' => $photos,
            'filter' => $filter,
            'groups' => $groups->map(fn ($g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'count' => (int) ($groupCounts[$g->id] ?? 0),
            ])->values(),
            'noGroupCount' => (int) ($groupCounts[''] ?? 0),
            'totalCount' => (int) $groupCounts->sum(),
        ]);
    }

    private function galleryOf(Event $event): PhotoGallery
    {
        return $event->photoGallery ?? abort(404);
    }

    /**
     * Cabeçalho comum das telas da galeria: evento, status e contagens das abas.
     *
     * @return array<string, mixed>
     */
    private function shell(Event $event, ?PhotoGallery $gallery): array
    {
        return [
            'event' => ['id' => $event->id, 'title' => $event->title, 'slug' => $event->slug],
            'exists' => $gallery !== null,
            'status' => $gallery?->status,
            'photoCount' => $gallery?->photos()->count() ?? 0,
            'peopleCount' => $gallery?->people()->count() ?? 0,
        ];
    }

    private function fromPrice(PhotoGallery $gallery): ?float
    {
        $tiers = $gallery->sortedTiers();

        return $tiers === [] ? null : Money::toReais((int) $tiers[0]['unit_price_cents']);
    }
}
