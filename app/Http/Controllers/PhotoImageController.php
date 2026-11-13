<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Services\Photos\PhotoGalleryAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega prévias, miniaturas e rostos do disco privado, só para quem tem
 * acesso à galeria. Sem acesso é 404, igual a arquivo que não existe.
 */
class PhotoImageController extends Controller
{
    /** Navegador guarda um dia: a grade não rebaixa tudo a cada visita. */
    private const MAX_AGE = 86400;

    public function photo(Request $request, PhotoGalleryAccess $access, Photo $photo, string $variant): Response
    {
        $path = match ($variant) {
            'thumb' => $photo->thumb_path,
            'preview' => $photo->preview_path,
            default => abort(404),
        };

        return $this->serve($request, $access, $photo->photo_gallery_id, $path);
    }

    public function face(Request $request, PhotoGalleryAccess $access, PhotoPerson $person): Response
    {
        abort_if($person->face_path === null, 404);

        return $this->serve($request, $access, $person->photo_gallery_id, $person->face_path);
    }

    private function serve(Request $request, PhotoGalleryAccess $access, int $galleryId, string $path): Response
    {
        $gallery = PhotoGallery::where('status', 'published')->find($galleryId);
        abort_unless($gallery !== null && $access->allows($gallery, $request), 404);

        $disk = Storage::disk(config('kena.photos.disk'));
        abort_unless($disk->exists($path), 404);

        $modified = $disk->lastModified($path);
        $etag = '"'.md5($path.'|'.$modified).'"';
        $headers = [
            'Cache-Control' => 'private, max-age='.self::MAX_AGE,
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modified).' GMT',
            'Vary' => 'Cookie',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return $disk->response($path, null, $headers);
    }
}
