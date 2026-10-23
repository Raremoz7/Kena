<?php

namespace App\Support\Presenters;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;

/** Converte os ingressos do usuário em TicketInfo (resources/js/lib/veludo/types.ts). */
final class TicketPresenter
{
    /** Ingresso nestes estados trava o reembolso do pedido inteiro. */
    private const LOCKING_STATUSES = [Ticket::STATUS_TRANSFERRED, Ticket::STATUS_USED];

    /** @return array<int, array<string, mixed>> */
    public static function forUser(User $user): array
    {
        $tickets = Ticket::query()
            ->where('user_id', $user->id)
            // O "tem ingresso transferido/usado?" vem junto, em vez de 1 query por ingresso.
            ->with(['session.event.venue', 'order' => fn ($q) => $q->withExists([
                'tickets as has_locked_tickets' => fn ($t) => $t->whereIn('status', self::LOCKING_STATUSES),
            ])])
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
            && $ticket->order->user_id === $ticket->user_id // destinatário de transferência não reembolsa pedido alheio
            && now()->lessThan($session->refundLocksAt())
            && ! self::orderHasLockedTickets($ticket->order);

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
            'status' => $ticket->status,
            'statusLabel' => self::statusLabel($ticket->status),
            'transferUrl' => route('tickets.transfer', $ticket),
            'canRefund' => $canRefund,
            'refundUrl' => route('orders.refund', $ticket->order_id),
            'calendarUrl' => route('tickets.calendar', $ticket),
            'googleWalletUrl' => route('tickets.google-wallet', $ticket),
        ];
    }

    private static function orderHasLockedTickets(Order $order): bool
    {
        // Carregado em lote por forUser(); quem chama item() avulso cai na query.
        if (array_key_exists('has_locked_tickets', $order->getAttributes())) {
            return (bool) $order->getAttribute('has_locked_tickets');
        }

        return $order->tickets()->whereIn('status', self::LOCKING_STATUSES)->exists();
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
