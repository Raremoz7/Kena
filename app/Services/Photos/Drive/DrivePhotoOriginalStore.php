<?php

namespace App\Services\Photos\Drive;

use App\Models\Photo;
use App\Services\Photos\PhotoOriginal;
use App\Services\Photos\PhotoOriginalStore;
use Illuminate\Support\Facades\Log;

/**
 * Originais guardados no Google Drive (`original_ref` = `drive:<id>`). Cada
 * entrega baixa o arquivo para um temporário no servidor; o controller
 * serve esse arquivo e o apaga depois. O comprador nunca recebe link do Drive.
 */
class DrivePhotoOriginalStore implements PhotoOriginalStore
{
    public function __construct(private readonly DriveClient $drive) {}

    public static function handles(Photo $photo): bool
    {
        return str_starts_with((string) $photo->original_ref, DriveFolderSource::REF_PREFIX);
    }

    /** Barato: só olha a referência, sem chamar o Drive (a lista de "minhas fotos" usa isto). */
    public function has(Photo $photo): bool
    {
        return self::handles($photo) && $this->fileId($photo) !== '';
    }

    public function open(Photo $photo): ?PhotoOriginal
    {
        if (! $this->has($photo)) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'kena-original-');

        try {
            $this->drive->download($this->fileId($photo), $path);
        } catch (DriveException $e) {
            @unlink($path);
            Log::error('Falha ao baixar o original do Drive.', ['photo' => $photo->id, 'erro' => $e->getMessage()]);

            return null;
        }

        return new PhotoOriginal(
            path: $path,
            downloadName: self::downloadName($photo),
            mimeType: 'image/jpeg',
            temporary: true,
        );
    }

    private function fileId(Photo $photo): string
    {
        return substr((string) $photo->original_ref, strlen(DriveFolderSource::REF_PREFIX));
    }

    private static function downloadName(Photo $photo): string
    {
        $base = pathinfo($photo->filename, PATHINFO_FILENAME) ?: 'foto-'.$photo->id;
        $ext = strtolower(pathinfo($photo->filename, PATHINFO_EXTENSION)) ?: 'jpg';

        return $base.'.'.$ext;
    }
}
