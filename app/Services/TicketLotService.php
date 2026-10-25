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
 *
 * Tudo passa por `snapshot()` (3 queries). Quem precisa de várias informações na
 * mesma tela pega um retrato e reaproveita, em vez de recontar a cada pergunta.
 */
class TicketLotService
{
    /** Retrato dos lotes da sessão. `$lock` trava os lotes (dentro da transação do hold). */
    public function snapshot(EventSession $session, bool $lock = false): LotSnapshot
    {
        /** @var Collection<int, TicketLot> $lots */
        $lots = TicketLot::query()
            ->where('session_id', $session->id)
            ->orderBy('position')
            ->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();

        if ($lots->isEmpty()) {
            return new LotSnapshot($lots, []);
        }

        $ids = $lots->modelKeys();

        $held = ReservationSeat::query()
            ->whereIn('lot_id', $ids)
            ->where('courtesy', false)
            ->whereHas('reservation', fn ($q) => $q
                ->where('status', Reservation::STATUS_ACTIVE)
                ->where('expires_at', '>', now()))
            ->selectRaw('lot_id, count(*) as total')
            ->groupBy('lot_id')
            ->pluck('total', 'lot_id');

        $sold = OrderItem::query()
            ->whereIn('lot_id', $ids)
            ->where('courtesy', false)
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->selectRaw('lot_id, count(*) as total')
            ->groupBy('lot_id')
            ->pluck('total', 'lot_id');

        $used = [];
        foreach ($ids as $id) {
            $used[$id] = (int) ($held[$id] ?? 0) + (int) ($sold[$id] ?? 0);
        }

        return new LotSnapshot($lots, $used);
    }

    /** Ingressos pagos que já contam no lote (segurados + vendidos). */
    public function used(TicketLot $lot): int
    {
        return $this->snapshot($lot->session)->used($lot);
    }

    /** Saldo do lote; null quando o lote não tem limite. */
    public function remaining(TicketLot $lot): ?int
    {
        return $this->snapshot($lot->session)->remaining($lot);
    }

    /** Lote vendendo agora, ou null (sessão sem lotes ou todos esgotados). */
    public function current(EventSession $session): ?TicketLot
    {
        return $this->snapshot($session)->current();
    }

    /** A sessão tem lotes e todos esgotaram. */
    public function soldOut(EventSession $session): bool
    {
        return $this->snapshot($session)->soldOut();
    }

    /** Preço do ingresso agora: do lote atual; null = usar o preço do setor. */
    public function currentPriceCents(EventSession $session): ?int
    {
        return $this->snapshot($session)->currentPriceCents();
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
        $snapshot = $this->snapshot($session, lock: true);

        if ($snapshot->isEmpty()) {
            return null;
        }

        $allocation = [];
        foreach ($snapshot->lots as $lot) {
            $left = $snapshot->remaining($lot);
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
        return $this->snapshot($session)->summary();
    }
}
