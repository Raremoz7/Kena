<?php

namespace App\Support\Presenters;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Money;

/** Converte os ingressos do usuário em TicketInfo (resources/js/lib/veludo/types.ts). */
final class TicketPresenter
{
    /** @return array<int, array<string, mixed>> */
    public static function forUser(User $user): array
    {
        $tickets = Ticket::query()
            ->where('user_id', $user->id)
            ->with(['session.event.venue', 'order'])
            ->latest('id')
            ->get();

        return $tickets->map(fn (Ticket $ticket): array => self::item($ticket))->all();
    }

    /** @return array<string, mixed> */
    public static function item(Ticket $ticket): array
    {
        $session = $ticket->session;
        $event = $session->event;

        $canRefund = $ticket->status === Ticket::STATUS_VALID
            && $ticket->order->status === Order::STATUS_PAID
            && now()->lessThan($session->refundLocksAt());

        return [
            'id' => $ticket->id,
            'eventTitle' => $event->title,
            'kicker' => $ticket->sector_name,
            'sectorName' => $ticket->sector_name,
            'seatLabel' => $ticket->seat_code,
            'dateLabel' => CatalogPresenter::sessionLabel($session),
            'venueName' => $event->venue->name,
            'holderName' => $ticket->holder_name,
            'code' => $ticket->code,
            'qrToken' => $ticket->qr_token,
            'price' => Money::toReais($ticket->price_cents),
            'status' => $ticket->status,
            'statusLabel' => self::statusLabel($ticket->status),
            'transferLocksAt' => $session->transferLocksAt()->toIso8601String(),
            'transferUrl' => route('tickets.transfer', $ticket),
            'canRefund' => $canRefund,
            'refundUrl' => route('orders.refund', $ticket->order_id),
            'calendarUrl' => route('tickets.calendar', $ticket),
            'googleWalletUrl' => route('tickets.google-wallet', $ticket),
        ];
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            Ticket::STATUS_USED => 'Utilizado',
            Ticket::STATUS_TRANSFERRED => 'Transferido',
            Ticket::STATUS_REFUNDED => 'Reembolsado',
            Ticket::STATUS_CANCELLED => 'Cancelado',
            default => 'Válido',
        };
    }
}
