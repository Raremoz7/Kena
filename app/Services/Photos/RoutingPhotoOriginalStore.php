<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Services\Photos\Drive\DrivePhotoOriginalStore;

/**
 * Escolhe onde está o original pelo prefixo do `original_ref`: `drive:<id>`
 * vai ao Google Drive; o resto é caminho dentro da pasta local.
 */
class RoutingPhotoOriginalStore implements PhotoOriginalStore
{
    public function __construct(
        private readonly LocalPhotoOriginalStore $local,
        private readonly DrivePhotoOriginalStore $drive,
    ) {}

    public function has(Photo $photo): bool
    {
        return $this->storeFor($photo)->has($photo);
    }

    public function open(Photo $photo): ?PhotoOriginal
    {
        return $this->storeFor($photo)->open($photo);
    }

    private function storeFor(Photo $photo): PhotoOriginalStore
    {
        return DrivePhotoOriginalStore::handles($photo) ? $this->drive : $this->local;
    }
}
