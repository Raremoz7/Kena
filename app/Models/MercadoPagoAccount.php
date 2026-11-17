<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Conta do Mercado Pago do organizador, autorizada por OAuth. Tokens
 * encriptados em repouso (APP_KEY) e nunca enviados ao frontend.
 *
 * @property int $id
 * @property string $mp_user_id
 * @property string|null $nickname
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string|null $public_key
 * @property string|null $scope
 * @property bool|null $live_mode
 * @property Carbon|null $expires_at
 * @property int|null $connected_by
 * @property Carbon $connected_at
 * @property Carbon|null $disconnected_at
 */
class MercadoPagoAccount extends Model
{
    protected $table = 'mercadopago_accounts';

    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'live_mode' => 'boolean',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    /**
     * Conta que recebe as vendas novas: a última conectada e não desconectada.
     * Com vários organizadores, este ponto passa a filtrar pelo dono do evento.
     */
    public static function current(): ?self
    {
        return static::query()
            ->whereNull('disconnected_at')
            ->latest('connected_at')
            ->latest('id')
            ->first();
    }

    /** @param Builder<self> $query */
    public function scopeConnected(Builder $query): void
    {
        $query->whereNull('disconnected_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
