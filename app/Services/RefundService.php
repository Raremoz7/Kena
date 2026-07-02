<?php

namespace App\Services;

use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Reembolso total de um pedido pago: estorna no gateway e libera os assentos. */
class RefundService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function refundOrder(Order $order, ?string $reason = null): Refund
    {
        $order->loadMissing(['payment', 'tickets']);

        if ($order->status !== Order::STATUS_PAID) {
            throw new \RuntimeException('Só pedidos pagos podem ser reembolsados.');
        }

        $payment = $order->payment;
        $gatewayOk = true;
        if ($payment !== null && $payment->gateway_payment_id !== null) {
            $gatewayOk = $this->gateway->refund($payment->gateway_payment_id);
        }

        $refund = DB::transaction(function () use ($order, $payment, $reason, $gatewayOk): Refund {
            $refund = Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $order->total_cents,
                'reason' => $reason,
                'status' => $gatewayOk ? Refund::STATUS_DONE : Refund::STATUS_FAILED,
                'gateway_refund_id' => null,
            ]);

            if (! $gatewayOk) {
                return $refund;
            }

            foreach ($order->tickets as $ticket) {
                $ticket->update(['status' => Ticket::STATUS_REFUNDED]);
                SessionSeat::where('id', $ticket->session_seat_id)->update([
                    'status' => SessionSeat::STATUS_AVAILABLE,
                    'sold_by_order_id' => null,
                ]);
            }

            $order->update(['status' => Order::STATUS_REFUNDED]);
            if ($payment !== null) {
                $payment->update(['status' => 'refunded']);
            }

            return $refund;
        });

        if ($refund->status === Refund::STATUS_DONE) {
            $order->loadMissing('user');
            if (filled($order->user->email)) {
                Mail::to($order->user->email)->queue(new RefundConfirmedMail($order));
            }
        }

        return $refund;
    }
}
