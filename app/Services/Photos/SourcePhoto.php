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
    /** @param  (Closure(): void)|null  $release  apaga o temporário baixado, quando a origem é remota */
    public function __construct(
        public readonly string $ref,
        public readonly string $filename,
        public readonly ?string $group,
        private readonly Closure $localPath,
        private readonly ?Closure $release = null,
    ) {}

    public function localPath(): string
    {
        return ($this->localPath)();
    }

    /** Libera o arquivo local depois de processado (no-op para pasta local). */
    public function release(): void
    {
        $this->release?->__invoke();
    }
}
