<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Models\PhotoGallery;
use RuntimeException;
use Throwable;

/**
 * Refaz a prévia e a miniatura de todas as fotos de uma galeria a partir dos
 * originais, mantendo os mesmos caminhos no disco. Serve para quando o titular
 * do direito autoral muda: o aviso desenhado e o XMP precisam acompanhar.
 *
 * Sem o original (foto de demonstração ou arquivo fora do lugar) não há como
 * regerar: a foto é contada em `missing` e fica como estava.
 */
final class PhotoRenditionsRegenerator
{
    public function __construct(
        private readonly PhotoOriginalStore $originals,
        private readonly XmpReader $xmp,
        private readonly PhotoRenditions $renditions,
    ) {}

    /**
     * @param  (callable(Photo, string): void)|null  $progress  "regerada", "sem original" ou "falhou"
     * @return array{done: int, missing: int, failed: array<int, string>}
     */
    public function regenerate(PhotoGallery $gallery, ?callable $progress = null): array
    {
        $holder = $gallery->copyrightHolder();
        $result = ['done' => 0, 'missing' => 0, 'failed' => []];

        foreach ($gallery->photos()->reorder('id')->lazy(50) as $photo) {
            $original = $this->originals->open($photo);
            if ($original === null) {
                $result['missing']++;
                if ($progress !== null) {
                    $progress($photo, 'sem original');
                }

                continue;
            }

            try {
                $this->one($photo, $original, $holder);
                $result['done']++;
                if ($progress !== null) {
                    $progress($photo, 'regerada');
                }
            } catch (Throwable $e) {
                $result['failed'][$photo->id] = $e->getMessage();
                if ($progress !== null) {
                    $progress($photo, 'falhou');
                }
            } finally {
                $original->release();
            }
        }

        return $result;
    }

    private function one(Photo $photo, PhotoOriginal $original, string $holder): void
    {
        $image = @imagecreatefromjpeg($original->path);
        if ($image === false) {
            throw new RuntimeException('Não é um JPEG válido.');
        }

        $image = PhotoImporter::orient($image, $this->xmp->read($original->path)->orientation);

        try {
            $this->renditions->preview($image, $photo->preview_path, $holder);
            $this->renditions->thumb($image, $photo->thumb_path, $holder);
        } finally {
            imagedestroy($image);
        }
    }
}
