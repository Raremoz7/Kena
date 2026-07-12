<?php

namespace App\Services;

use App\Mail\TicketsIssuedMail;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Emite os ingressos quando o pedido é pago: marca assentos vendidos + cria tickets. */
class TicketIssuanceService
{
    public function __construct(private readonly QrTokenService $qr) {}

    /**
     * Emissão serializada e defensiva: relê o pedido com lock (webhook e
     * reconciliação podem chegar juntos), recusa pedido não-pagável e só
     * reivindica assento que ainda pertence a este pedido — nunca sobrescreve
     * um assento revendido a outro comprador.
     */
    public function issueForOrder(Order $order): IssueOutcome
    {
        try {
            $outcome = $this->issueInTransaction($order);
        } catch (SeatsUnavailableForIssuance) {
            return IssueOutcome::SeatsUnavailable;
        }

        // E-mail de confirmação com ingressos + QR (só na emissão nova).
        if ($outcome === IssueOutcome::Issued) {
            $order->refresh()->loadMissing('user');
            if (filled($order->user->email)) {
                Mail::to($order->user->email)->queue(new TicketsIssuedMail($order));
            }
        }

        return $outcome;
    }

    private function issueInTransaction(Order $order): IssueOutcome
    {
        return DB::transaction(function () use ($order): IssueOutcome {
            // Fonte da verdade: estado atual do pedido, serializado pelo lock.
            $fresh = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $fresh->loadMissing(['items', 'user']);

            // Idempotente: se já pago e com ingressos, não reemite.
            if ($fresh->status === Order::STATUS_PAID && $fresh->tickets()->exists()) {
                return IssueOutcome::AlreadyIssued;
            }

            // Pedido cancelado/reembolsado/falho não pode reviver com aprovação atrasada.
            if (! in_array($fresh->status, [Order::STATUS_PENDING, Order::STATUS_PAID], true)) {
                return IssueOutcome::NotPayable;
            }

            foreach ($fresh->items as $item) {
                // Só reivindica o assento se ele ainda é deste pedido: em hold
                // da reserva original ou liberado (ninguém levou). Assento
                // vendido/segurado por outro → aprovação chegou tarde.
                $claimed = SessionSeat::whereKey($item->session_seat_id)
                    ->where(function ($query) use ($fresh): void {
                        $query
                            ->where(function ($held) use ($fresh): void {
                                $held->where('status', SessionSeat::STATUS_HELD)
                                    ->where('held_by_reservation_id', $fresh->reservation_id);
                            })
                            ->orWhere('status', SessionSeat::STATUS_AVAILABLE);
                    })
                    ->update([
                        'status' => SessionSeat::STATUS_SOLD,
                        'hold_expires_at' => null,
                        'held_by_reservation_id' => null,
                        'sold_by_order_id' => $fresh->id,
                    ]);

                if ($claimed === 0) {
                    // Rollback total: nenhum ingresso parcial.
                    throw new SeatsUnavailableForIssuance;
                }

                $code = Codes::ticket();
                Ticket::create([
                    'order_id' => $fresh->id,
                    'order_item_id' => $item->id,
                    'session_id' => $fresh->session_id,
                    'user_id' => $fresh->user_id,
                    'session_seat_id' => $item->session_seat_id,
                    'code' => $code,
                    'qr_token' => $this->qr->issue($code),
                    'holder_name' => $fresh->user->name,
                    'seat_code' => $item->seat_code,
                    'sector_name' => $item->sector_name,
                    'price_cents' => $item->price_cents,
                    'status' => Ticket::STATUS_VALID,
                ]);
            }

            $fresh->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            $fresh->loadMissing('reservation');
            if ($fresh->reservation !== null) {
                $fresh->reservation->update(['status' => Reservation::STATUS_CONVERTED]);
            }

            return IssueOutcome::Issued;
        });
    }
}
