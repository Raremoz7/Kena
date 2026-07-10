<?php

namespace App\Support\Presenters;

use App\Models\Reservation;
use App\Support\Money;

/** Converte a reserva no payload de checkout (ReservationInfo). */
final class CheckoutPresenter
{
    /** @return array<string, mixed> */
    public static function reservation(Reservation $reservation): array
    {
        $reservation->loadMissing(['seats.sessionSeat.seat', 'seats.sessionSeat.sector', 'session.event']);

        return [
            'expiresAt' => $reservation->expires_at->toIso8601String(),
            'eventTitle' => $reservation->session->event->title,
            'sessionLabel' => CatalogPresenter::sessionLabel($reservation->session),
            'seats' => $reservation->seats->map(fn ($rs): array => [
                'id' => $rs->session_seat_id,
                'code' => $rs->sessionSeat->seat->code,
                'sectorName' => $rs->sessionSeat->sector->name,
                'price' => Money::toReais($rs->price_cents),
            ])->all(),
        ];
    }
}
