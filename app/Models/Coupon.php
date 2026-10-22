<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $code
 * @property string|null $beneficiary
 * @property string|null $group_name
 * @property string $type
 * @property int $value
 * @property int|null $max_uses
 * @property int $used
 * @property Carbon|null $expires_at
 * @property bool $active
 * @property-read Event|null $event
 */
class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /** Cortesia: `value` ingressos saem de graça (os mais caros do pedido). */
    public const TYPE_TICKETS = 'tickets';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isRedeemable(?int $eventId = null): bool
    {
        if (! $this->active) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses !== null && $this->used >= $this->max_uses) {
            return false;
        }
        if ($this->event_id !== null && $eventId !== null && $this->event_id !== $eventId) {
            return false;
        }

        return true;
    }

    public function isCourtesy(): bool
    {
        return $this->type === self::TYPE_TICKETS;
    }

    /**
     * Desconto em centavos para os ingressos do pedido (preço de cada um).
     *
     * @param  array<int, int>  $prices
     */
    public function discountForPrices(array $prices): int
    {
        if ($this->isCourtesy()) {
            return array_sum(array_slice(self::sortedDesc($prices), 0, max(0, $this->value)));
        }

        return $this->discountFor(array_sum($prices));
    }

    /**
     * Quais posições de `$prices` saem como cortesia (as mais caras).
     *
     * @param  array<int, int>  $prices
     * @return array<int, int> chaves de `$prices`
     */
    public function courtesyKeys(array $prices): array
    {
        if (! $this->isCourtesy()) {
            return [];
        }

        arsort($prices);

        return array_slice(array_keys($prices), 0, max(0, $this->value));
    }

    /**
     * @param  array<int, int>  $prices
     * @return array<int, int>
     */
    private static function sortedDesc(array $prices): array
    {
        rsort($prices);

        return $prices;
    }

    /** Desconto em centavos sobre um subtotal (também em centavos). */
    public function discountFor(int $subtotalCents): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotalCents * $this->value / 100)
            : $this->value;

        return min($discount, $subtotalCents);
    }
}
