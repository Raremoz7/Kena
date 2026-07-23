<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    }
}
