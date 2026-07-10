<?php

namespace App\Services;

use App\Mail\OrderCancelledMail;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Cancela uma sessão: fecha a venda primeiro, reembolsa os pagos (com e-mail),
 * cancela pendentes (avisando por e-mail e matando Pix vivo no gateway) e
 * libera TODOS os holds ativos — inclusive reservas ainda sem pedido.
 */
class SessionCancellationService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly SeatReservationService $reservations,
        private readonly PaymentGateway $gateway,
    ) {}

    /** @return array{refunded: int, cancelled: int, failed: int} */
    public function cancel(EventSession $session): array
    {
        // Fecha a porta primeiro: some da venda e o pay() recusa novos pagamentos.
        $session->update(['status' => 'cancelled']);

        $refunded = 0;
        $cancelled = 0;
        $failed = 0;

        // Reembolsa pagos (refundOrder cuida de assentos, ingressos e e-mail).
        $paid = Order::where('session_id', $session->id)
            ->where('status', Order::STATUS_PAID)
            ->get();
        foreach ($paid as $order) {
            try {
                $refund = $this->refunds->refundOrder($order, 'Sessão cancelada pelo organizador');
                $refund->status === Refund::STATUS_DONE ? $refunded++ : $failed++;
            } catch (\RuntimeException) {
                $failed++;
            }
        }

        // Cancela pendentes: mata pagamento vivo no gateway, libera hold, avisa.
        $pending = Order::where('session_id', $session->id)
            ->where('status', Order::STATUS_PENDING)
            ->with(['reservation', 'user'])
            ->get();
        foreach ($pending as $order) {
            $livePayments = Payment::where('order_id', $order->id)
                ->where('status', Payment::STATUS_PENDING)
                ->whereNotNull('gateway_payment_id')
                ->get();
            foreach ($livePayments as $payment) {
                $this->gateway->cancelPayment((string) $payment->gateway_payment_id);
                $payment->update(['status' => Payment::STATUS_CANCELLED]);
            }

            DB::transaction(function () use ($order): void {
                $order->update(['status' => Order::STATUS_CANCELLED]);
                if ($order->reservation !== null) {
                    $this->reservations->release($order->reservation);
                }
            });

            if (filled($order->user->email)) {
                Mail::to($order->user->email)
                    ->queue(new OrderCancelledMail($order, 'A sessão foi cancelada pelo organizador. Nenhuma cobrança foi efetivada.'));
            }
            $cancelled++;
        }

        // Holds ativos sem pedido (gente ainda escolhendo pagamento).
        Reservation::where('session_id', $session->id)
            ->where('status', Reservation::STATUS_ACTIVE)
            ->get()
            ->each(fn (Reservation $reservation) => $this->reservations->release($reservation));

        return ['refunded' => $refunded, 'cancelled' => $cancelled, 'failed' => $failed];
    }
}
