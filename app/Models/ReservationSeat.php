<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reservation_id
 * @property int $session_seat_id
 * @property int $price_cents
 * @property int|null $lot_id
 * @property bool $courtesy
 * @property bool $requires_courtesy
 * @property-read Reservation $reservation
 * @property-read SessionSeat $sessionSeat
 */
class ReservationSeat extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'courtesy' => 'boolean',
            'requires_courtesy' => 'boolean',
        ];
    }

    /** @return BelongsTo<Reservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** @return BelongsTo<SessionSeat, $this> */
    public function sessionSeat(): BelongsTo
    {
        return $this->belongsTo(SessionSeat::class);
    }
}
