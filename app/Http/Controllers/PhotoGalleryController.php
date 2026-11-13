<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoGroup;
use App\Models\PhotoPerson;
use App\Services\Photos\PhotoOrderService;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fotos do espetáculo à venda: lista de galerias e a galeria de um evento.
 */
class PhotoGalleryController extends Controller
{
    public function index(): Response
    {
        $galleries = PhotoGallery::with(['event.venue', 'event.sessions', 'event.sectors'])
            ->withCount(['photos', 'people'])
            ->where('status', 'published')
            ->latest('published_at')
            ->get()
            ->map(fn (PhotoGallery $g): array => [
                'event' => CatalogPresenter::listItem($g->event),
                'photoCount' => $g->photos_count,
                'peopleCount' => $g->people_count,
                'covers' => $g->photos()->limit(3)->pluck('thumb_path')->map(fn ($p) => self::url($p)),
                'tiers' => self::tiers($g),
            ]);

        return Inertia::render('buyer/photos', ['galleries' => $galleries]);
    }

    public function show(Request $request, string $slug, PhotoOrderService $photoOrders): Response
    {
        $user = $request->user('web');

        $gallery = PhotoGallery::with(['event.venue', 'event.sessions', 'event.sectors', 'groups', 'people'])
            ->whereHas('event', fn ($q) => $q->where('slug', $slug))
            ->where('status', 'published')
            ->firstOrFail();

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
                    'face' => $p->face_path ? self::url($p->face_path) : null,
                    'count' => $counts[$p->id] ?? 0,
                ])
                ->filter(fn (array $p): bool => $p['count'] > 0)
                ->values(),
            'photos' => $photos->map(fn (Photo $p): array => [
                'id' => $p->id,
                'thumb' => self::url($p->thumb_path),
                'preview' => self::url($p->preview_path),
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

    /** @return array<int, array{minQty: int, unitPrice: float}> */
    private static function tiers(PhotoGallery $gallery): array
    {
        return array_map(fn (array $t): array => [
            'minQty' => (int) $t['min_qty'],
            'unitPrice' => Money::toReais((int) $t['unit_price_cents']),
        ], $gallery->sortedTiers());
    }

    /** Caminho relativo: funciona em qualquer porta/host sem depender do APP_URL. */
    private static function url(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }
}
