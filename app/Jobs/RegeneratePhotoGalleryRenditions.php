<?php

namespace App\Jobs;

use App\Models\PhotoGallery;
use App\Services\Photos\PhotoRenditionsRegenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Refaz as prévias da galeria em segundo plano (titular do direito autoral mudou). */
class RegeneratePhotoGalleryRenditions implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly int $galleryId) {}

    public function handle(PhotoRenditionsRegenerator $regenerator): void
    {
        ini_set('memory_limit', '1G');

        $gallery = PhotoGallery::find($this->galleryId);
        if ($gallery !== null) {
            $regenerator->regenerate($gallery);
        }
    }
}
