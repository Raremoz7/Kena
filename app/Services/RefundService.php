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
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CouponService $coupons,
    ) {}

    public function refundOrder(Order $order, ?string $reason = null): Refund
    {
        $order->loadMissing(['payment', 'tickets', 'session']);

        // Claim atômico: só quem virar o status PAID→REFUNDED prossegue —
        // duas requisições simultâneas não registram dois estornos.
        $claimed = Order::whereKey($order->id)
            ->where('status', Order::STATUS_PAID)
            ->update(['status' => Order::STATUS_REFUNDED]);

        if ($claimed === 0) {
            throw new \RuntimeException('Só pedidos pagos podem ser reembolsados.');
        }

        $payment = $order->payment;
        $gatewayOk = true;
        if ($payment !== null && $payment->gateway_payment_id !== null) {
            $gatewayOk = $this->gateway->refund($payment->gateway_payment_id);
        }

        if (! $gatewayOk) {
            // Estorno recusado: devolve o pedido a PAID e registra a falha
            // para o painel — nada de assento/ingresso muda.
            $order->update(['status' => Order::STATUS_PAID]);

            return Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $order->total_cents,
                'reason' => $reason,
                'status' => Refund::STATUS_FAILED,
                'gateway_refund_id' => null,
            ]);
        }

        return $this->settle($order, $reason);
    }

    /**
     * Estorno feito fora do Kena (painel do Mercado Pago ou chargeback do banco):
     * o dinheiro já voltou, então só cancela ingressos e libera os assentos.
     * Devolve null se o pedido já não estava pago (ex.: reembolso do painel do
     * Kena, cujo webhook de "refunded" chega depois).
     */
    public function recordExternalRefund(Order $order, string $reason): ?Refund
    {
        $order->loadMissing(['payment', 'tickets', 'session']);

        $claimed = Order::whereKey($order->id)
            ->where('status', Order::STATUS_PAID)
            ->update(['status' => Order::STATUS_REFUNDED]);

        if ($claimed === 0) {
            return null;
        }

        return $this->settle($order, $reason);
    }

    /** Registra o reembolso concluído, cancela ingressos, libera assentos e avisa o comprador. */
    private function settle(Order $order, ?string $reason): Refund
    {
        $payment = $order->payment;

        $refund = DB::transaction(function () use ($order, $payment, $reason): Refund {
            $refund = Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $order->total_cents,
                'reason' => $reason,
                'status' => Refund::STATUS_DONE,
                'gateway_refund_id' => null,
            ]);

            // Assento só volta pra venda se a sessão ainda não começou —
            // reembolso tardio não pode recolocar lugar de show em andamento.
            $sessionUpcoming = $order->session->starts_at->isFuture();

            foreach ($order->tickets as $ticket) {
                $ticket->update(['status' => Ticket::STATUS_REFUNDED]);
                if ($sessionUpcoming) {
                    SessionSeat::where('id', $ticket->session_seat_id)->update([
                        'status' => SessionSeat::STATUS_AVAILABLE,
                        'sold_by_order_id' => null,
                    ]);
                }
            }

            if ($payment !== null) {
                $payment->update(['status' => 'refunded']);
            }

            // Cupom/cortesia volta a valer: o pai que estornou a cortesia para
            // trocar de lugar consegue usar o mesmo código de novo.
            $this->coupons->release($order);

            return $refund;
        });

        $order->loadMissing('user');
        if (filled($order->user->email)) {
            Mail::to($order->user->email)->queue(new RefundConfirmedMail($order, $reason));
        }

        return $refund;
    }
}
