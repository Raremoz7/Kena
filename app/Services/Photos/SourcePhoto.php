<?php

namespace App\Services\Photos;

use Closure;

/**
 * Uma foto encontrada na origem. `ref` identifica o original de forma estável
 * (caminho relativo à pasta do evento) e é a chave da idempotência.
 */
final class SourcePhoto
{
    /** @param  Closure(): string  $localPath  devolve um arquivo local legível (a origem remota baixa aqui) */
    public function __construct(
        public readonly string $ref,
        public readonly string $filename,
        public readonly ?string $group,
        private readonly Closure $localPath,
    ) {}

    public function localPath(): string
    {
        return ($this->localPath)();
    }
}
