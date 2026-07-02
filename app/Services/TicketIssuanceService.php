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

    public function issueForOrder(Order $order): void
    {
        $issued = DB::transaction(function () use ($order): bool {
            $order->loadMissing(['items', 'user', 'reservation']);

            // Idempotente: se já pago e com ingressos, não reemite.
            if ($order->status === Order::STATUS_PAID && $order->tickets()->exists()) {
                return false;
            }

            foreach ($order->items as $item) {
                SessionSeat::where('id', $item->session_seat_id)->update([
                    'status' => SessionSeat::STATUS_SOLD,
                    'hold_expires_at' => null,
                    'held_by_reservation_id' => null,
                    'sold_by_order_id' => $order->id,
                ]);

                $code = Codes::ticket();
                Ticket::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'session_id' => $order->session_id,
                    'user_id' => $order->user_id,
                    'session_seat_id' => $item->session_seat_id,
                    'code' => $code,
                    'qr_token' => $this->qr->issue($code),
                    'holder_name' => $order->user->name,
                    'seat_code' => $item->seat_code,
                    'sector_name' => $item->sector_name,
                    'price_cents' => $item->price_cents,
                    'status' => Ticket::STATUS_VALID,
                ]);
            }

            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

            if ($order->reservation !== null) {
                $order->reservation->update(['status' => Reservation::STATUS_CONVERTED]);
            }

            return true;
        });

        // E-mail de confirmação com ingressos + QR (só na emissão nova).
        if ($issued) {
            $order->loadMissing('user');
            if (filled($order->user->email)) {
                Mail::to($order->user->email)->queue(new TicketsIssuedMail($order));
            }
        }
    }
}
