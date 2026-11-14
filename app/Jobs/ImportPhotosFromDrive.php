<?php

namespace App\Jobs;

use App\Models\PhotoGallery;
use App\Services\Photos\Drive\DriveClient;
use App\Services\Photos\Drive\DriveException;
use App\Services\Photos\Drive\DriveFolderSource;
use App\Services\Photos\PhotoImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use Throwable;

/**
 * Importa as fotos da pasta do Drive da galeria. Roda na fila porque baixar e
 * processar centenas de originais leva minutos. O resultado fica na própria
 * galeria (`import_status` e `import_report`) para o painel mostrar.
 */
class ImportPhotosFromDrive implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $galleryId) {}

    public function handle(PhotoImporter $importer, DriveClient $drive): void
    {
        $gallery = PhotoGallery::find($this->galleryId);
        if ($gallery === null) {
            return;
        }

        $gallery->update(['import_status' => 'running', 'import_report' => null]);

        // Originais em alta ocupam bastante memória ao decodificar.
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && (int) $limit < 1024 && ! str_contains((string) $limit, 'G')) {
            ini_set('memory_limit', '1G');
        }

        try {
            $source = new DriveFolderSource($drive, (string) $gallery->drive_folder);
            $result = $importer->import($gallery, $source);

            $gallery->update([
                'import_status' => 'done',
                'import_report' => $result->toArray(),
                'import_finished_at' => now(),
            ]);
        } catch (DriveException|InvalidArgumentException $e) {
            $this->markFailed($gallery, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        $gallery = PhotoGallery::find($this->galleryId);
        if ($gallery !== null) {
            $this->markFailed($gallery, 'A importação parou por um erro inesperado: '.$e->getMessage());
        }
    }

    private function markFailed(PhotoGallery $gallery, string $message): void
    {
        $gallery->update([
            'import_status' => 'failed',
            'import_report' => ['error' => $message],
            'import_finished_at' => now(),
        ]);
    }
}
