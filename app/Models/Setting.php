<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;

/**
 * Configurações editáveis pelo painel (ex.: credenciais do Mercado Pago).
 * O valor é encriptado em repouso (cast `encrypted`, usa a APP_KEY).
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'encrypted'];
    }

    /** @return array<string, string|null> */
    public static function map(): array
    {
        return static::all()->mapWithKeys(fn (self $s): array => [$s->key => $s->value])->all();
    }

    /**
     * Mapa lido uma vez por requisição (ou job da fila) para o boot: o Mercado
     * Pago e o e-mail usam o mesmo, em vez de 2 consultas cada a toda visita.
     * Null sem banco/tabela (ex.: package:discover antes das migrações).
     *
     * @return array<string, string|null>|null
     */
    public static function forBoot(): ?array
    {
        return once(static function (): ?array {
            try {
                return Schema::hasTable('settings') ? static::map() : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Mantém o nullsafe: first() pode retornar null em runtime, mesmo o
        // PHPStan inferindo o contrário.
        // @phpstan-ignore-next-line nullsafe.neverNull
        return static::query()->where('key', $key)->first()?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Once::flush();
    }
}
