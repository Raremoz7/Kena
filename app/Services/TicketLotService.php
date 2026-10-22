<?php

namespace App\Services;

use App\Exceptions\LotsSoldOutException;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\TicketLot;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lotes de venda. Um lote "consome" um ingresso quando ele está segurado numa
 * reserva ativa ou vendido num pedido pago — cortesia não consome. O lote atual
 * é o primeiro (por posição) que ainda tem saldo; sem lotes, vale o preço do setor.
 */
class TicketLotService
{
    /** @return Collection<int, TicketLot> */
    public function lotsFor(EventSession $session): Collection
    {
        return $session->lots()->get();
    }

    /** Ingressos pagos que já contam no lote (segurados + vendidos). */
    public function used(TicketLot $lot): int
    {
        $held = ReservationSeat::query()
            ->where('lot_id', $lot->id)
            ->where('courtesy', false)
            ->whereHas('reservation', fn ($q) => $q
                ->where('status', Reservation::STATUS_ACTIVE)
                ->where('expires_at', '>', now()))
            ->count();

        $sold = OrderItem::query()
            ->where('lot_id', $lot->id)
            ->where('courtesy', false)
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->count();

        return $held + $sold;
    }

    /** Saldo do lote; null quando o lote não tem limite. */
    public function remaining(TicketLot $lot): ?int
    {
        return $lot->quantity === null ? null : max(0, $lot->quantity - $this->used($lot));
    }

    /** Lote vendendo agora, ou null (sessão sem lotes ou todos esgotados). */
    public function current(EventSession $session): ?TicketLot
    {
        foreach ($this->lotsFor($session) as $lot) {
            $left = $this->remaining($lot);
            if ($left === null || $left > 0) {
                return $lot;
            }
        }

        return null;
    }

    /** A sessão tem lotes e todos esgotaram. */
    public function soldOut(EventSession $session): bool
    {
        return $this->lotsFor($session)->isNotEmpty() && $this->current($session) === null;
    }

    /** Preço do ingresso agora: do lote atual; null = usar o preço do setor. */
    public function currentPriceCents(EventSession $session): ?int
    {
        return $this->current($session)?->price_cents;
    }

    /**
     * Distribui `$count` ingressos pelos lotes com saldo, em ordem: se o lote
     * atual só tem 1 lugar e o comprador leva 3, o 1º sai no preço dele e os
     * outros 2 no próximo. Chamar dentro da transação do hold (trava os lotes).
     *
     * @return array<int, TicketLot>|null um lote por ingresso, ou null se a sessão não tem lotes
     *
     * @throws LotsSoldOutException
     */
    public function allocate(EventSession $session, int $count): ?array
    {
        /** @var Collection<int, TicketLot> $lots */
        $lots = TicketLot::query()
            ->where('session_id', $session->id)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lots->isEmpty()) {
            return null;
        }

        $allocation = [];
        foreach ($lots as $lot) {
            $left = $this->remaining($lot);
            $take = $left === null ? $count - count($allocation) : min($left, $count - count($allocation));

            for ($i = 0; $i < $take; $i++) {
                $allocation[] = $lot;
            }

            if (count($allocation) === $count) {
                return $allocation;
            }
        }

        // O que coube é o que ainda resta à venda em todos os lotes.
        throw new LotsSoldOutException(count($allocation));
    }

    /**
     * Resumo para o painel e para o site.
     *
     * @return array<int, array{id: int, name: string, priceCents: int, quantity: int|null, used: int, remaining: int|null, state: string}>
     */
    public function summary(EventSession $session): array
    {
        $current = $this->current($session);
        $seenCurrent = false;

        return $this->lotsFor($session)->map(function (TicketLot $lot) use ($current, &$seenCurrent): array {
            // Antes do atual: esgotado. O atual: vendendo. Depois: próximo.
            if ($current !== null && $lot->id === $current->id) {
                $state = 'current';
                $seenCurrent = true;
            } else {
                $state = $seenCurrent ? 'next' : 'sold_out';
            }

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'priceCents' => $lot->price_cents,
                'quantity' => $lot->quantity,
                'used' => $this->used($lot),
                'remaining' => $this->remaining($lot),
                'state' => $state,
            ];
        })->values()->all();
    }
}
