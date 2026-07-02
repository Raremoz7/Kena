<?php

namespace App\Support;

/** Conversões de dinheiro. O domínio guarda centavos (int); o front recebe reais. */
final class Money
{
    /** Centavos → reais (number) para o payload do front. */
    public static function toReais(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
