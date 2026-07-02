<?php

namespace Tests\Feature\Kena\Concerns;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Seat;
use App\Models\Sector;
use App\Models\SessionSeat;
use App\Models\Venue;
use Illuminate\Support\Str;

trait MakesKenaData
{
    protected function makeSession(int $seats = 3, int $priceCents = 4500): EventSession
    {
        $venue = Venue::create([
            'name' => 'Teatro Teste '.Str::random(5),
            'city' => 'Brasília',
            'state' => 'DF',
        ]);

        $event = Event::create([
            'venue_id' => $venue->id,
            'slug' => 'evento-'.Str::random(8),
            'title' => 'Evento Teste',
            'kicker' => 'Teste',
            'description' => 'Descrição.',
            'status' => 'on_sale',
        ]);

        $sector = Sector::create([
            'event_id' => $event->id,
            'name' => 'Plateia',
            'price_cents' => $priceCents,
        ]);

        $session = EventSession::create([
            'event_id' => $event->id,
            'starts_at' => now()->addDays(30),
            'status' => 'on_sale',
        ]);

        for ($i = 1; $i <= $seats; $i++) {
            $seat = Seat::create([
                'venue_id' => $venue->id,
                'code' => 'A'.$i,
                'line' => 'A',
                'number' => (string) $i,
                'pos_x' => $i * 10,
                'pos_y' => 10,
                'kind' => 'standard',
            ]);
            SessionSeat::create([
                'session_id' => $session->id,
                'seat_id' => $seat->id,
                'sector_id' => $sector->id,
                'price_cents' => $priceCents,
                'status' => SessionSeat::STATUS_AVAILABLE,
            ]);
        }

        return $session;
    }

    /** @return array<int, int> */
    protected function availableSeatIds(EventSession $session, int $limit): array
    {
        return SessionSeat::where('session_id', $session->id)
            ->where('status', SessionSeat::STATUS_AVAILABLE)
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
