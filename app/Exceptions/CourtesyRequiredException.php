<?php

namespace App\Exceptions;

/** A venda paga esgotou e parte dos lugares segurados só fecha com cortesia. */
class CourtesyRequiredException extends \RuntimeException
{
    public function __construct(public readonly int $seats)
    {
        parent::__construct($seats === 1
            ? 'Os ingressos à venda esgotaram: 1 dos seus lugares é da cota de cortesia. Use seu código de cortesia ou escolha menos lugares.'
            : "Os ingressos à venda esgotaram: {$seats} dos seus lugares são da cota de cortesia. Use seu código de cortesia ou escolha menos lugares.");
    }
}
