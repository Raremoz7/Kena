<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $event_id
 * @property string $code
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

    /** Desconto em centavos sobre um subtotal (também em centavos). */
    public function discountFor(int $subtotalCents): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotalCents * $this->value / 100)
            : $this->value;

        return min($discount, $subtotalCents);
    }
}
