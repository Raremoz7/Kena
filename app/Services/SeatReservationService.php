<?php

namespace App\Services;

use App\Exceptions\LotsSoldOutException;
use App\Exceptions\SeatConflictException;
use App\Exceptions\SeatLimitException;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\TicketLot;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reserva (hold) de assentos com trava pessimista. A transação + lockForUpdate
 * serializa concorrentes — no SQLite a própria trava de escrita garante isso.
 */
class SeatReservationService
{
    public function __construct(
        private readonly TicketLotService $lots,
        private readonly CourtesyReserveService $reserve,
        private readonly CouponService $coupons,
        private readonly PaymentGateway $gateway,
    ) {}

    public function holdMinutes(): int
    {
        return (int) config('kena.hold_minutes', 10);
    }

    public function maxTicketsPerBuyer(): int
    {
        return max(1, (int) config('kena.max_tickets_per_buyer', 8));
    }

    /** Quantos ingressos o comprador ainda pode pegar nesta sessão. */
    public function remainingFor(EventSession $session, ?User $user): int
    {
        if ($user === null) {
            return $this->maxTicketsPerBuyer();
        }

        $owned = Ticket::query()
            ->where('session_id', $session->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->count();

        return max(0, $this->maxTicketsPerBuyer() - $owned);
    }

    /**
     * Segura os assentos para o usuário, criando uma reserva ativa.
     *
     * @param  array<int, int>  $sessionSeatIds
     *
     * @throws SeatConflictException
     * @throws SeatLimitException
     * @throws LotsSoldOutException
     */
    public function hold(EventSession $session, User $user, array $sessionSeatIds): Reservation
    {
        $ids = array_values(array_unique(array_map('intval', $sessionSeatIds)));
        if ($ids === []) {
            throw new SeatConflictException([]);
        }

        // A reserva anterior do mesmo comprador é substituída, então só os
        // ingressos já comprados contam contra o limite.
        $remaining = $this->remainingFor($session, $user);
        if (count($ids) > $remaining) {
            throw new SeatLimitException($this->maxTicketsPerBuyer(), $remaining);
        }

        return DB::transaction(function () use ($session, $user, $ids): Reservation {
            // Libera holds vencidos desta sessão antes de avaliar disponibilidade.
            $this->releaseExpiredForSession($session->id);

            // Solta qualquer reserva ativa anterior do mesmo usuário nesta sessão.
            $this->cancelActiveReservationsForUser($session->id, $user->id);

            /** @var Collection<int, SessionSeat> $seats */
            $seats = SessionSeat::query()
                ->where('session_id', $session->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->with('seat')
                ->get();

            $conflicts = [];
            foreach ($seats as $seat) {
                if ($seat->status !== SessionSeat::STATUS_AVAILABLE) {
                    $conflicts[] = $seat->seat->code;
                }
            }
            // Algum id inexistente também é conflito.
            if ($seats->count() !== count($ids)) {
                $conflicts[] = '—';
            }
            if ($conflicts !== []) {
                throw new SeatConflictException(array_values(array_unique($conflicts)));
            }

            // Cota de cortesia: o que passar da venda paga só fecha com código
            // de cortesia no pagamento (o comprador escolhe o lugar antes).
            $paidCount = $this->paidSlots($session, $seats->count());

            // Com lotes, o preço de cada ingresso vem do lote em que ele cai.
            $allocation = $this->allocatePaid($session, $paidCount);
            $courtesyPrice = $paidCount < $seats->count() ? $this->courtesyPriceCents($session) : null;

            $reservation = Reservation::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
                'status' => Reservation::STATUS_ACTIVE,
                'expires_at' => now()->addMinutes($this->holdMinutes()),
            ]);

            foreach ($seats->values() as $i => $seat) {
                $requiresCourtesy = $i >= $paidCount;
                $lot = $requiresCourtesy ? null : ($allocation[$i] ?? null);
                $price = $lot->price_cents ?? ($requiresCourtesy ? $courtesyPrice : null) ?? $seat->price_cents;

                $seat->update([
                    'status' => SessionSeat::STATUS_HELD,
                    'hold_expires_at' => $reservation->expires_at,
                    'held_by_reservation_id' => $reservation->id,
                    // O assento guarda o preço em que foi vendido (receita do painel).
                    'price_cents' => $price,
                ]);

                ReservationSeat::create([
                    'reservation_id' => $reservation->id,
                    'session_seat_id' => $seat->id,
                    'price_cents' => $price,
                    'lot_id' => $lot?->id,
                    // Fora da venda paga: conta na cota de cortesia, não no lote.
                    'courtesy' => $requiresCourtesy,
                    'requires_courtesy' => $requiresCourtesy,
                ]);
            }

            return $reservation;
        });
    }

    /**
     * Quantos dos `$count` lugares cabem na venda paga. Sem cota, todos (os
     * lotes continuam valendo como antes). Trava a sessão para serializar
     * holds concorrentes na conta da cota.
     */
    private function paidSlots(EventSession $session, int $count): int
    {
        $locked = EventSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
        $paidLeft = $this->reserve->paidLeft($locked);

        return $paidLeft === null ? $count : min($count, $paidLeft);
    }

    /**
     * Distribui os pagos pelos lotes. Com cota, lote esgotado não barra o
     * hold: o que não couber vira lugar de cortesia (o pai com código ainda
     * consegue escolher). Sem cota, segue o esgotado de sempre.
     *
     * @return array<int, TicketLot>|null
     *
     * @throws LotsSoldOutException
     */
    private function allocatePaid(EventSession $session, int &$paidCount): ?array
    {
        try {
            return $this->lots->allocate($session, $paidCount);
        } catch (LotsSoldOutException $e) {
            if ((int) $session->courtesy_reserve <= 0) {
                throw $e;
            }
            $paidCount = $e->remaining;

            return $this->lots->allocate($session, $paidCount);
        }
    }

    /** Preço de vitrine do lugar de cortesia: lote atual (ou o último); sem lotes, o do setor. */
    private function courtesyPriceCents(EventSession $session): ?int
    {
        $snapshot = $this->lots->snapshot($session);

        $current = $snapshot->current();

        return $current !== null ? $current->price_cents : $snapshot->lots->last()?->price_cents;
    }

    /** Minutos a mais que o comprador pode pedir no checkout (uma vez). */
    public const EXTENSION_MINUTES = 5;

    /**
     * "Preciso de mais tempo" (WCAG 2.2.1): estende a reserva ativa uma única vez.
     * Reserva já estendida (inclusive pelo Pix) ou vencida não estende de novo.
     *
     * @return bool se estendeu
     */
    public function extend(Reservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation): bool {
            /** @var Reservation $fresh */
            $fresh = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            $alreadyExtended = $fresh->expires_at->greaterThan(
                $fresh->created_at->copy()->addMinutes($this->holdMinutes() + 1),
            );
            if (! $fresh->isActive() || $alreadyExtended) {
                return false;
            }

            $until = $fresh->expires_at->copy()->addMinutes(self::EXTENSION_MINUTES);
            $fresh->update(['expires_at' => $until]);
            SessionSeat::query()
                ->where('held_by_reservation_id', $fresh->id)
                ->where('status', SessionSeat::STATUS_HELD)
                ->update(['hold_expires_at' => $until]);

            $reservation->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    /**
     * Libera a reserva (volta assentos a disponível) e marca como cancelada.
     * Quem desiste (cancelar, trocar de lugares) não pode continuar com um Pix
     * pagável: o pagamento vivo é cancelado no gateway antes, e o pedido junto.
     */
    public function release(Reservation $reservation): void
    {
        $this->cancelLivePayments($reservation);

        DB::transaction(function () use ($reservation): void {
            SessionSeat::query()
                ->where('held_by_reservation_id', $reservation->id)
                ->where('status', SessionSeat::STATUS_HELD)
                ->update([
                    'status' => SessionSeat::STATUS_AVAILABLE,
                    'hold_expires_at' => null,
                    'held_by_reservation_id' => null,
                ]);

            $reservation->update(['status' => Reservation::STATUS_CANCELLED]);
            $this->abandonPendingOrders([$reservation->id]);
        });
    }

    /** Expira todos os holds vencidos do sistema (job agendado). */
    public function expireDueHolds(): int
    {
        return DB::transaction(function (): int {
            $count = SessionSeat::query()
                ->where('status', SessionSeat::STATUS_HELD)
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '<=', now())
                ->update([
                    'status' => SessionSeat::STATUS_AVAILABLE,
                    'hold_expires_at' => null,
                    'held_by_reservation_id' => null,
                ]);

            $expired = Reservation::query()
                ->where('status', Reservation::STATUS_ACTIVE)
                ->where('expires_at', '<=', now())
                ->pluck('id')
                ->all();

            Reservation::query()
                ->whereIn('id', $expired)
                ->update(['status' => Reservation::STATUS_EXPIRED]);
            $this->abandonPendingOrders($expired);

            return $count;
        });
    }

    /**
     * Reserva que morreu sem virar venda (trocou de lugares, venceu): o pedido
     * pendente é cancelado e o cupom/cortesia volta a valer. Sem isso, a
     * cortesia de uso único ficava "gasta" num pedido abandonado e o pai via
     * "cupom inválido" na tentativa seguinte.
     *
     * @param  array<int, int>  $reservationIds
     */
    private function abandonPendingOrders(array $reservationIds): void
    {
        if ($reservationIds === []) {
            return;
        }

        // Pagamento ainda vivo no gateway (Pix aguardando, cartão em análise)
        // fica de fora: ele mesmo devolve o cupom ao falhar ou vencer.
        $orders = Order::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('status', Order::STATUS_PENDING)
            ->whereDoesntHave('payment', fn ($q) => $q->where('status', Payment::STATUS_PENDING))
            ->get();

        foreach ($orders as $order) {
            $order->update(['status' => Order::STATUS_CANCELLED]);
            $this->coupons->release($order);
        }
    }

    private function cancelLivePayments(Reservation $reservation): void
    {
        $live = Payment::query()
            ->whereIn('order_id', Order::where('reservation_id', $reservation->id)
                ->where('status', Order::STATUS_PENDING)
                ->select('id'))
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_payment_id')
            ->get();

        foreach ($live as $payment) {
            $this->gateway->cancelPayment((string) $payment->gateway_payment_id);
            $payment->update(['status' => Payment::STATUS_CANCELLED]);
        }
    }

    private function releaseExpiredForSession(int $sessionId): void
    {
        SessionSeat::query()
            ->where('session_id', $sessionId)
            ->where('status', SessionSeat::STATUS_HELD)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->update([
                'status' => SessionSeat::STATUS_AVAILABLE,
                'hold_expires_at' => null,
                'held_by_reservation_id' => null,
            ]);
    }

    private function cancelActiveReservationsForUser(int $sessionId, int $userId): void
    {
        $reservations = Reservation::query()
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->where('status', Reservation::STATUS_ACTIVE)
            ->get();

        foreach ($reservations as $reservation) {
            $this->release($reservation);
        }
    }
}
