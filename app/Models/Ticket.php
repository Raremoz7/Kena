<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $order_item_id
 * @property int $session_id
 * @property int $user_id
 * @property int $session_seat_id
 * @property string $code
 * @property string $qr_token
 * @property string $holder_name
 * @property string $seat_code
 * @property string $sector_name
 * @property int $price_cents
 * @property string $status
 * @property Carbon|null $checked_in_at
 * @property-read Order $order
 * @property-read EventSession $session
 * @property-read User $user
 */
class Ticket extends Model
{
    public const STATUS_VALID = 'valid';

    public const STATUS_USED = 'used';

    public const STATUS_TRANSFERRED = 'transferred';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
