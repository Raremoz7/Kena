<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $session_seat_id
 * @property string $seat_code
 * @property string $sector_name
 * @property int $price_cents
 * @property-read Order $order
 * @property-read SessionSeat $sessionSeat
 */
class OrderItem extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<SessionSeat, $this> */
    public function sessionSeat(): BelongsTo
    {
        return $this->belongsTo(SessionSeat::class);
    }
}
