<?php

namespace App\Services;

use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\SessionSeat;

/**
 * Cota de cortesia da sessão. Nenhum lugar fica separado: a cota é só uma
 * quantidade. Ingressos pagos (segurados numa reserva ativa ou vendidos) vão
 * até (lugares do mapa − cota); os lugares livres que sobram só saem com
 * código de cortesia, escolhidos por quem chegar, onde quiser.
 */
class CourtesyReserveService
{
    /** Lugares do mapa que podem ser vendidos (bloqueados ficam de fora). */
    public function capacity(EventSession $session): int
    {
        return SessionSeat::query()
            ->where('session_id', $session->id)
            ->where('status', '!=', SessionSeat::STATUS_BLOCKED)
            ->count();
    }

    /** Ingressos pagos que já ocupam a sessão (segurados + vendidos). */
    public function paidUsed(EventSession $session): int
    {
        return $this->used($session, courtesy: false);
    }

    /** Ingressos de cortesia que já ocupam a sessão (segurados + emitidos). */
    public function courtesyUsed(EventSession $session): int
    {
        return $this->used($session, courtesy: true);
    }

    /** Quantos ingressos pagos ainda cabem; null = sessão sem cota (sem limite extra). */
    public function paidLeft(EventSession $session): ?int
    {
        $reserve = (int) $session->courtesy_reserve;
        if ($reserve <= 0) {
            return null;
        }

        return max(0, $this->capacity($session) - $reserve - $this->paidUsed($session));
    }

    /**
     * Resumo para o painel.
     *
     * @return array{reserve: int, capacity: int, paidUsed: int, paidLeft: int|null, courtesyUsed: int, reserveLeft: int}
     */
    public function summary(EventSession $session): array
    {
        $reserve = (int) $session->courtesy_reserve;
        $courtesyUsed = $this->courtesyUsed($session);

        return [
            'reserve' => $reserve,
            'capacity' => $this->capacity($session),
            'paidUsed' => $this->paidUsed($session),
            'paidLeft' => $this->paidLeft($session),
            'courtesyUsed' => $courtesyUsed,
            'reserveLeft' => max(0, $reserve - $courtesyUsed),
        ];
    }

    /** Mesma contagem dos lotes: reserva ativa e não vencida, ou pedido pago. */
    private function used(EventSession $session, bool $courtesy): int
    {
        $held = ReservationSeat::query()
            ->where('courtesy', $courtesy)
            ->whereHas('reservation', fn ($q) => $q
                ->where('session_id', $session->id)
                ->where('status', Reservation::STATUS_ACTIVE)
                ->where('expires_at', '>', now()))
            ->count();

        $sold = OrderItem::query()
            ->where('courtesy', $courtesy)
            ->whereHas('order', fn ($q) => $q
                ->where('session_id', $session->id)
                ->where('status', Order::STATUS_PAID))
            ->count();

        return $held + $sold;
    }
}
