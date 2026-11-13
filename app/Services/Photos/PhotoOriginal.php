<?php

namespace App\Services\Photos;

/**
 * Original pronto para entregar: um arquivo legível neste servidor. Uma
 * implementação remota (Drive) baixa para um temporário e marca `temporary`,
 * para quem entrega apagar depois.
 */
final class PhotoOriginal
{
    public function __construct(
        public readonly string $path,
        public readonly string $downloadName,
        public readonly string $mimeType,
        public readonly bool $temporary = false,
    ) {}

    /** Apaga o arquivo temporário (no-op para arquivo local permanente). */
    public function release(): void
    {
        if ($this->temporary && is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
