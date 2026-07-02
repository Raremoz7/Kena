<?php

namespace App\Exceptions;

use RuntimeException;

/** Um ou mais assentos já não estão disponíveis no momento da reserva. */
class SeatConflictException extends RuntimeException
{
    /** @param array<int, string> $seatCodes */
    public function __construct(public readonly array $seatCodes)
    {
        parent::__construct('Assentos indisponíveis: '.implode(', ', $seatCodes));
    }
}
