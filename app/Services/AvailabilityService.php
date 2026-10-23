<?php

namespace App\Services;

use App\Models\EventSession;
use App\Models\SessionSeat;
use Illuminate\Support\Facades\Cache;

/** Snapshot leve de disponibilidade para o polling do mapa. */
class AvailabilityService
{
    /** Quanto tempo o retrato do polling fica em cache (s): muitos pais no mapa = 1 consulta. */
    public const CACHE_SECONDS = 2;

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

    /**
     * Só os lugares que NÃO estão livres (o resto é "available"). É o que o
     * polling do mapa recebe: a resposta encolhe de 500 lugares para os ocupados.
     *
     * @return array<int, string> [sessionSeatId => status]
     */
    public function unavailable(EventSession $session): array
    {
        return Cache::remember(
            'availability:'.$session->id,
            self::CACHE_SECONDS,
            fn (): array => array_filter(
                $this->snapshot($session),
                fn (string $status): bool => $status !== SessionSeat::STATUS_AVAILABLE,
            ),
        );
    }
}
