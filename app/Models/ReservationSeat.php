<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reservation_id
 * @property int $session_seat_id
 * @property int $price_cents
 * @property-read Reservation $reservation
 * @property-read SessionSeat $sessionSeat
 */
class ReservationSeat extends Model
{
    protected $guarded = [];

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
