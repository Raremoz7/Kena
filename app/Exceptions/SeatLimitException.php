<?php

namespace App\Exceptions;

use RuntimeException;

/** O comprador passaria do máximo de ingressos por sessão. */
class SeatLimitException extends RuntimeException
{
    public function __construct(public readonly int $max, public readonly int $remaining)
    {
        parent::__construct("Limite de {$max} ingressos por comprador; restam {$remaining}.");
    }
}
