<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property Carbon $starts_at
 * @property Carbon|null $doors_at
 * @property string $status
 * @property Carbon|null $reminded_at
 * @property-read Event $event
 */
class EventSession extends Model
{
    protected $table = 'event_sessions';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'doors_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<SessionSeat, $this> */
    public function sessionSeats(): HasMany
    {
        return $this->hasMany(SessionSeat::class, 'session_id');
    }

    /** Sessão vendável: evento publicado, sessão não cancelada e ainda no futuro. */
    public function isSellable(): bool
    {
        $this->loadMissing('event');

        return $this->status !== 'cancelled'
            && $this->starts_at->isFuture()
            && in_array($this->event->status, ['on_sale', 'sold_out'], true);
    }

    /** Transferência bloqueada quando faltam <= 24h para a sessão. */
    public function transferLocksAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subDay();
    }

    /** Reembolso self-service do comprador bloqueado a partir deste momento. */
    public function refundLocksAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subHours((int) config('kena.refund_deadline_hours', 48));
    }
}
