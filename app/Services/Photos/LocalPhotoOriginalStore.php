<?php

namespace App\Services\Photos;

use App\Models\Photo;

/**
 * Originais numa pasta local (config kena.photos.originals_path, fora da
 * pasta pública). `original_ref` é o caminho relativo dentro dela; nada fora
 * da pasta é servido, mesmo com "../" no valor.
 */
class LocalPhotoOriginalStore implements PhotoOriginalStore
{
    public function has(Photo $photo): bool
    {
        return $this->resolve($photo) !== null;
    }

    public function open(Photo $photo): ?PhotoOriginal
    {
        $path = $this->resolve($photo);
        if ($path === null) {
            return null;
        }

        return new PhotoOriginal(
            path: $path,
            downloadName: self::downloadName($photo, $path),
            mimeType: mime_content_type($path) ?: 'application/octet-stream',
        );
    }

    /** Caminho real do original, só se existir e estiver dentro da pasta configurada. */
    private function resolve(Photo $photo): ?string
    {
        $ref = $photo->original_ref;
        if ($ref === null || $ref === '') {
            return null;
        }

        $root = realpath((string) config('kena.photos.originals_path'));
        $path = $root !== false ? realpath($root.DIRECTORY_SEPARATOR.ltrim($ref, '/\\')) : false;
        if ($root === false || $path === false || ! is_file($path)
            || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $path;
    }

    /** Nome do arquivo baixado: o nome original, com a extensão do arquivo real. */
    public static function downloadName(Photo $photo, string $path): string
    {
        $base = pathinfo($photo->filename, PATHINFO_FILENAME) ?: 'foto-'.$photo->id;
        $ext = pathinfo($path, PATHINFO_EXTENSION) ?: pathinfo($photo->filename, PATHINFO_EXTENSION) ?: 'jpg';

        return $base.'.'.strtolower($ext);
    }
}
