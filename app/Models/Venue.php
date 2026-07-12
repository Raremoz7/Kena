<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $city
 * @property string $state
 * @property string|null $address
 * @property string|null $maps_query
 * @property int|null $seats_count
 * @property int|null $events_count
 */
class Venue extends Model
{
    protected $guarded = [];

    /** @return HasMany<Seat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Há venda viva em qualquer sessão deste local? (assento vendido/segurado ou
     * ingresso emitido). Enquanto não houver, o mapa pode ser reeditado com segurança.
     */
    public function hasSales(): bool
    {
        return SessionSeat::query()
            ->whereIn('session_id', EventSession::whereIn('event_id', $this->events()->select('id'))->select('id'))
            ->whereIn('status', [SessionSeat::STATUS_SOLD, SessionSeat::STATUS_HELD])
            ->exists();
    }
}
