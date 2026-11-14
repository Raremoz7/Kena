<?php

namespace App\Services\Photos;

/**
 * Pessoa marcada numa foto. A região segue o padrão MWG (centro x/y, largura e
 * altura normalizados) e é nula quando só existe a palavra-chave com o nome.
 */
final readonly class TaggedPerson
{
    /** @param  array{x: float, y: float, w: float, h: float}|null  $region */
    public function __construct(public string $name, public ?array $region = null) {}
}
