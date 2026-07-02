<?php

namespace App\Services;

use App\Models\EventSession;
use App\Models\Seat;
use App\Models\Sector;
use App\Models\SessionSeat;
use Illuminate\Support\Facades\DB;

/**
 * Materializa os `session_seats` de uma sessão a partir dos assentos físicos do
 * venue, todos no setor informado e disponíveis. Idempotente (pula os já criados).
 */
class SessionSeatGenerator
{
    public function generate(EventSession $session, Sector $sector, int $venueId): int
    {
        $seatIds = Seat::where('venue_id', $venueId)->pluck('id');
        $taken = SessionSeat::where('session_id', $session->id)->pluck('seat_id')->flip();
        $now = now();

        $rows = [];
        foreach ($seatIds as $seatId) {
            if ($taken->has($seatId)) {
                continue;
            }
            $rows[] = [
                'session_id' => $session->id,
                'seat_id' => $seatId,
                'sector_id' => $sector->id,
                'price_cents' => $sector->price_cents,
                'status' => SessionSeat::STATUS_AVAILABLE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('session_seats')->insert($chunk);
        }

        return count($rows);
    }
}
