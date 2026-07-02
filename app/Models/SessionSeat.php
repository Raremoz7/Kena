<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Estado de um assento numa sessão.
 *
 * @property int $id
 * @property int $session_id
 * @property int $seat_id
 * @property int $sector_id
 * @property int $price_cents
 * @property string $status
 * @property Carbon|null $hold_expires_at
 * @property int|null $held_by_reservation_id
 * @property int|null $sold_by_order_id
 * @property-read EventSession $session
 * @property-read Seat $seat
 * @property-read Sector $sector
 */
class SessionSeat extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_HELD = 'held';

    public const STATUS_SOLD = 'sold';

    public const STATUS_BLOCKED = 'blocked';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hold_expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    /** @return BelongsTo<Seat, $this> */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** Hold vencido conta como disponível para fins de exibição. */
    public function isEffectivelyAvailable(): bool
    {
        if ($this->status === self::STATUS_AVAILABLE) {
            return true;
        }

        return $this->status === self::STATUS_HELD
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast();
    }
}
