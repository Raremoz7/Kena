<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property int $user_id
 * @property string $status
 * @property Carbon $expires_at
 * @property-read EventSession $session
 * @property-read User $user
 * @property-read Collection<int, ReservationSeat> $seats
 */
class Reservation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CONVERTED = 'converted';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
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

    /** @return HasMany<ReservationSeat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    /** Reserva em andamento do comprador (ativa e dentro do prazo), se houver. */
    public static function inProgressFor(int $userId): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }
}
