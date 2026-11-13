<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\User;
use App\Services\Photos\PhotoOrderService;
use App\Services\Photos\PhotoOriginal;
use App\Services\Photos\PhotoOriginalStore;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Minhas fotos": as fotos compradas (pedido pago) com download de cada uma
 * e de todas num .zip. Todo download passa por aqui, que confere a compra a
 * cada pedido; o endereço do original nunca chega ao navegador.
 */
class MyPhotosController extends Controller
{
    public function __construct(
        private readonly PhotoOrderService $photoOrders,
        private readonly PhotoOriginalStore $originals,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user('web');

        $items = OrderPhoto::query()
            ->whereNotNull('photo_id')
            ->whereHas('order', fn ($q) => $q->photoOrders()
                ->where('user_id', $user->id)
                ->where('status', Order::STATUS_PAID))
            ->with(['photo', 'order:id,photo_gallery_id,paid_at'])
            ->get()
            ->filter(fn (OrderPhoto $item): bool => $item->photo !== null)
            ->unique('photo_id');

        $galleries = PhotoGallery::with('event.venue', 'event.sessions', 'event.sectors')
            ->whereIn('id', $items->pluck('photo.photo_gallery_id')->unique())
            ->get()
            ->keyBy('id');

        $groups = $items
            ->groupBy(fn (OrderPhoto $item): int => $item->photo->photo_gallery_id)
            ->map(function (Collection $group, int $galleryId) use ($galleries): array {
                /** @var PhotoGallery $gallery */
                $gallery = $galleries[$galleryId];
                $photos = $group->sortBy(fn (OrderPhoto $i): int => $i->photo->position)->values();

                return [
                    'galleryId' => $galleryId,
                    'event' => CatalogPresenter::listItem($gallery->event),
                    'galleryUrl' => route('photos.show', $gallery->event->slug),
                    'zipUrl' => route('photos.mine.zip', $galleryId),
                    'photos' => $photos->map(fn (OrderPhoto $item): array => [
                        'id' => $item->photo->id,
                        'thumb' => '/storage/'.ltrim($item->photo->thumb_path, '/'),
                        'filename' => $item->filename,
                        'width' => $item->photo->width,
                        'height' => $item->photo->height,
                        // Sem original guardado ainda (ex.: foto de demonstração): avisa em vez de baixar.
                        'available' => $this->originals->has($item->photo),
                        'downloadUrl' => route('photos.mine.download', $item->photo->id),
                    ])->all(),
                    'paidAt' => $group->max(fn (OrderPhoto $i) => $i->order->paid_at)?->format('d/m/Y'),
                ];
            })
            ->sortByDesc('paidAt')
            ->values();

        return Inertia::render('buyer/my-photos', [
            'galleries' => $groups,
            'needsPassword' => $user->password === null,
        ]);
    }

    public function download(Request $request, Photo $photo): BinaryFileResponse|RedirectResponse
    {
        $this->ensureOwned($request, $photo);

        $original = $this->originals->open($photo);
        if ($original === null) {
            return back()->with('warning', 'O original desta foto ainda está sendo preparado pelo fotógrafo. Assim que chegar, o download fica disponível aqui.');
        }

        $response = response()->download($original->path, $original->downloadName, [
            'Content-Type' => $original->mimeType,
            'Cache-Control' => 'private, no-store',
        ]);
        if ($original->temporary) {
            $response->deleteFileAfterSend();
        }

        return $response;
    }

    /** Todas as fotos compradas de uma galeria num .zip. */
    public function zip(Request $request, PhotoGallery $gallery): BinaryFileResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');
        $ids = $this->photoOrders->ownedPhotoIds($user);
        $photos = $gallery->photos()->whereIn('id', $ids)->get();
        abort_if($photos->isEmpty(), 404);

        $zipPath = tempnam(sys_get_temp_dir(), 'kena-fotos-');
        $zip = new \ZipArchive;
        if ($zipPath === false || $zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            return back()->with('warning', 'Não foi possível montar o .zip agora. Tente de novo em instantes.');
        }

        $opened = [];
        $missing = [];
        $usedNames = [];
        foreach ($photos as $photo) {
            $original = $this->originals->open($photo);
            if ($original === null) {
                $missing[] = $photo->filename;

                continue;
            }
            $opened[] = $original;
            $zip->addFile($original->path, self::uniqueName($original, $usedNames));
            // Foto já é comprimida: guardar sem recomprimir economiza CPU.
            $zip->setCompressionName(end($usedNames), \ZipArchive::CM_STORE);
        }

        if ($opened === []) {
            $zip->close();
            @unlink($zipPath);

            return back()->with('warning', 'Os originais destas fotos ainda estão sendo preparados. Assim que chegarem, o .zip fica disponível aqui.');
        }

        if ($missing !== []) {
            $zip->addFromString('LEIA-ME.txt', "Estas fotos ainda não têm o original disponível e ficaram de fora deste arquivo:\r\n\r\n"
                .implode("\r\n", $missing)
                ."\r\n\r\nAssim que chegarem, baixe de novo em Minhas fotos.\r\n");
        }
        $zip->close();
        foreach ($opened as $original) {
            $original->release();
        }

        $gallery->loadMissing('event');
        $name = 'fotos-'.Str::slug((string) $gallery->event->title).'.zip';

        return response()->download($zipPath, $name, [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    /** Só quem comprou (pedido pago) baixa. Para os outros a foto "não existe". */
    private function ensureOwned(Request $request, Photo $photo): void
    {
        /** @var User $user */
        $user = $request->user('web');
        abort_unless($this->photoOrders->owns($user, $photo->id), 404);
    }

    /** @param  array<int, string>  $used */
    private static function uniqueName(PhotoOriginal $original, array &$used): string
    {
        $name = $original->downloadName;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $n = 1;
        while (in_array($name, $used, true)) {
            $name = $base.'-'.(++$n).($ext !== '' ? '.'.$ext : '');
        }
        $used[] = $name;

        return $name;
    }
}
