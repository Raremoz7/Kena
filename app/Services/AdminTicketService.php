<?php

namespace App\Services;

use App\Exceptions\SeatConflictException;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Codes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ingressos emitidos pela organização (ex.: a fileira da gestão): grátis,
 * nominais, com QR e e-mail para cada pessoa. Vale para lugar livre ou
 * bloqueado; não conta no lote nem no limite por comprador.
 */
class AdminTicketService
{
    public function __construct(private readonly TicketIssuanceService $issuance) {}

    /**
     * @param  array<int, array{session_seat_id: int, name: string, email: string}>  $tickets
     * @return int ingressos emitidos
     *
     * @throws SeatConflictException
     */
    public function issue(EventSession $session, array $tickets): int
    {
        return DB::transaction(function () use ($session, $tickets): int {
            $ids = array_map(fn (array $t): int => (int) $t['session_seat_id'], $tickets);

            /** @var Collection<int, SessionSeat> $seats */
            $seats = SessionSeat::query()
                ->where('session_id', $session->id)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->with(['seat', 'sector'])
                ->get()
                ->keyBy('id');

            $conflicts = $seats
                ->reject(fn (SessionSeat $s): bool => in_array($s->status, [SessionSeat::STATUS_AVAILABLE, SessionSeat::STATUS_BLOCKED], true))
                ->map(fn (SessionSeat $s): string => $s->seat->code)
                ->values()
                ->all();
            if ($seats->count() !== count(array_unique($ids))) {
                $conflicts[] = '—';
            }
            if ($conflicts !== []) {
                throw new SeatConflictException($conflicts);
            }

            // Um pedido por e-mail: cada pessoa recebe os próprios ingressos.
            $byEmail = collect($tickets)->groupBy(fn (array $t): string => EmailVerificationCodeService::normalize($t['email']));

            foreach ($byEmail as $email => $group) {
                $this->issueForPerson($session, (string) $email, $group->all(), $seats);
            }

            return count($tickets);
        });
    }

    /**
     * @param  array<int, array{session_seat_id: int, name: string, email: string}>  $tickets
     * @param  Collection<int, SessionSeat>  $seats
     */
    private function issueForPerson(EventSession $session, string $email, array $tickets, Collection $seats): void
    {
        $user = User::where('email', $email)->first()
            ?? User::create(['email' => $email, 'name' => $tickets[0]['name']]);

        $reservation = Reservation::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'status' => Reservation::STATUS_ACTIVE,
            'expires_at' => now()->addMinutes(10),
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'session_id' => $session->id,
            'reservation_id' => $reservation->id,
            'reference' => Codes::orderReference(),
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 0,
            'status' => Order::STATUS_PENDING,
        ]);

        foreach ($tickets as $t) {
            /** @var SessionSeat $seat */
            $seat = $seats->get((int) $t['session_seat_id']);

            // Segura o lugar para este pedido (bloqueado também), como num checkout.
            $seat->update([
                'status' => SessionSeat::STATUS_HELD,
                'hold_expires_at' => $reservation->expires_at,
                'held_by_reservation_id' => $reservation->id,
            ]);

            ReservationSeat::create([
                'reservation_id' => $reservation->id,
                'session_seat_id' => $seat->id,
                'price_cents' => 0,
                'courtesy' => true,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'session_seat_id' => $seat->id,
                'seat_code' => $seat->seat->code,
                'sector_name' => $seat->sector->name,
                'price_cents' => 0,
                'courtesy' => true,
            ]);
        }

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'kena',
            'method' => Payment::METHOD_FREE,
            'status' => Payment::STATUS_APPROVED,
            'amount_cents' => 0,
        ]);

        $this->issuance->issueForOrder($order);

        // Cada ingresso leva o nome de quem vai usar (o e-mail pode ser o mesmo).
        foreach ($tickets as $t) {
            Ticket::where('order_id', $order->id)
                ->where('session_seat_id', (int) $t['session_seat_id'])
                ->update(['holder_name' => $t['name']]);
        }
    }
}
