<?php

namespace App\Services;

use App\Models\EventSession;
use App\Models\SessionSeat;

/** Snapshot leve de disponibilidade para o polling do mapa. */
class AvailabilityService
{
    /**
     * Estado efetivo de cada session_seat da sessão.
     *
     * @return array<int, string> [sessionSeatId => status]
     */
    public function snapshot(EventSession $session): array
    {
        return SessionSeat::query()
            ->where('session_id', $session->id)
            ->get(['id', 'status', 'hold_expires_at'])
            ->mapWithKeys(fn (SessionSeat $ss): array => [
                $ss->id => $ss->isEffectivelyAvailable() ? SessionSeat::STATUS_AVAILABLE : $ss->status,
            ])
            ->all();
    }
}
