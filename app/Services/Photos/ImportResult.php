<?php

namespace App\Services\Photos;

/** Contagem de uma rodada do importador. */
final class ImportResult
{
    public int $imported = 0;

    public int $skipped = 0;

    /** Pessoas novas criadas na galeria. */
    public int $people = 0;

    /** @var array<string, string> referência da foto => motivo */
    public array $failed = [];
}
