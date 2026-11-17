<?php

namespace App\Models;

use App\Services\Payments\MercadoPagoOAuth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Organizador de eventos (tenant). Eventos, locais, cupons, pedidos, equipe e
 * conta do Mercado Pago pertencem a uma organização.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $document
 * @property string|null $contact_email
 * @property string $status
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property string|null $service_fee_percent
 * @property string|null $photo_fee_percent
 * @property string|null $statement_descriptor
 * @property int|null $pix_expiration_minutes
 * @property Carbon|null $terms_accepted_at
 */
class Organization extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /** Organização criada pela migração com todos os dados de antes do multi-tenant. */
    public const DEFAULT_ID = 1;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'pix_expiration_minutes' => 'integer',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /** Taxa de serviço desta organização (exceção negociada) ou a padrão da plataforma. */
    public function serviceFeePercent(): float
    {
        return $this->service_fee_percent !== null
            ? (float) $this->service_fee_percent
            : (float) config('kena.service_fee_percent', 6);
    }

    /**
     * Pode vender: não suspensa e, com o OAuth ligado, com conta do Mercado
     * Pago conectada (a venda cai direto na conta dela).
     */
    public function canSell(): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return ! MercadoPagoOAuth::enabled() || MercadoPagoAccount::forOrganization($this->id) !== null;
    }

    /**
     * Organizações que aparecem e vendem no site: ativas e, com OAuth, com
     * conta do Mercado Pago conectada (mesma regra de canSell()).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSelling(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);

        if (MercadoPagoOAuth::enabled()) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('mercadopago_accounts')
                ->whereColumn('mercadopago_accounts.organization_id', 'organizations.id')
                ->whereNull('mercadopago_accounts.disconnected_at'));
        }
    }

    /** Taxa das fotos desta organização (separada da do ingresso) ou a padrão. */
    public function photoFeePercent(): float
    {
        return $this->photo_fee_percent !== null
            ? (float) $this->photo_fee_percent
            : (float) config('kena.photo_fee_percent', 6);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<PanelUser, $this> */
    public function panelUsers(): HasMany
    {
        return $this->hasMany(PanelUser::class);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organizador';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
