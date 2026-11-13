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

    /** @return array{imported: int, skipped: int, people: int, failed: array<string, string>} */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'people' => $this->people,
            'failed' => $this->failed,
        ];
    }
}
