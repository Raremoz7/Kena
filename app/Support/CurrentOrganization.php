<?php

namespace App\Support;

/**
 * Organização do usuário do painel na requisição atual. Enquanto definida,
 * os models multi-tenant só enxergam dados dela (ver BelongsToOrganization).
 * Fora do painel (site do comprador, fila, comandos) fica vazia e nada é filtrado.
 */
final class CurrentOrganization
{
    private static ?int $id = null;

    public static function set(?int $id): void
    {
        self::$id = $id;
    }

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function clear(): void
    {
        self::$id = null;
    }

    /**
     * Roda $callback dentro do contexto de uma organização, restaurando o anterior.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(?int $id, callable $callback): mixed
    {
        $previous = self::$id;
        self::$id = $id;

        try {
            return $callback();
        } finally {
            self::$id = $previous;
        }
    }
}
