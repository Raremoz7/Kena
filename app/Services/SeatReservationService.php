<?php

namespace App\Services;

use App\Exceptions\SeatConflictException;
use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\SessionSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reserva (hold) de assentos com trava pessimista. A transação + lockForUpdate
 * serializa concorrentes — no SQLite a própria trava de escrita garante isso.
 */
class SeatReservationService
{
    public function holdMinutes(): int
    {
        return (int) config('kena.hold_minutes', 10);
    }

    /**
     * Segura os assentos para o usuário, criando uma reserva ativa.
     *
     * @param  array<int, int>  $sessionSeatIds
     *
     * @throws SeatConflictException
     */
    public function hold(EventSession $session, User $user, array $sessionSeatIds): Reservation
    {
        $ids = array_values(array_unique(array_map('intval', $sessionSeatIds)));
        if ($ids === []) {
            throw new SeatConflictException([]);
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

            $reservation = Reservation::create([
                'session_id' => $session->id,
                'user_id' => $user->id,
                'status' => Reservation::STATUS_ACTIVE,
                'expires_at' => now()->addMinutes($this->holdMinutes()),
            ]);

            foreach ($seats as $seat) {
                $seat->update([
                    'status' => SessionSeat::STATUS_HELD,
                    'hold_expires_at' => $reservation->expires_at,
                    'held_by_reservation_id' => $reservation->id,
                ]);

                ReservationSeat::create([
                    'reservation_id' => $reservation->id,
                    'session_seat_id' => $seat->id,
                    'price_cents' => $seat->price_cents,
                ]);
            }

            return $reservation;
        });
    }

    /** Libera a reserva (volta assentos a disponível) e marca como cancelada. */
    public function release(Reservation $reservation): void
    {
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

            Reservation::query()
                ->where('status', Reservation::STATUS_ACTIVE)
                ->where('expires_at', '<=', now())
                ->update(['status' => Reservation::STATUS_EXPIRED]);

            return $count;
        });
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
