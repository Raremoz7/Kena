<?php

namespace App\Exceptions;

use RuntimeException;

/** Todos os lotes da sessão esgotaram (ou não cabem os ingressos pedidos). */
class LotsSoldOutException extends RuntimeException
{
    public function __construct(public readonly int $remaining)
    {
        parent::__construct('Lotes esgotados.');
    }
}
