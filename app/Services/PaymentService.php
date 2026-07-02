<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Orquestra pagamento: cria pedido, cobra no gateway e reconcilia status. */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly OrderService $orders,
        private readonly CouponService $coupons,
        private readonly TicketIssuanceService $issuance,
        private readonly SeatReservationService $reservations,
    ) {}

    /**
     * Cria o pedido a partir da reserva e dispara o pagamento.
     *
     * @param  array{method?: string, token?: string, installments?: int, payment_method_id?: string, coupon_code?: string}  $input
     */
    public function pay(Reservation $reservation, array $input): Order
    {
        $coupon = $this->coupons->resolve($input['coupon_code'] ?? null, $reservation->session->event_id);
        $order = $this->orders->createFromReservation($reservation, $coupon);

        if ($order->status === Order::STATUS_PAID) {
            return $order;
        }

        $order->loadMissing('user');
        $email = (string) $order->user->email;
        $doc = $order->user->cpf;

        if (($input['method'] ?? 'card') === Payment::METHOD_PIX) {
            $result = $this->gateway->createPix($order, $email, $doc);
            $method = Payment::METHOD_PIX;
        } else {
            $result = $this->gateway->chargeCard(
                $order,
                (string) ($input['token'] ?? ''),
                (int) ($input['installments'] ?? 1),
                $input['payment_method_id'] ?? null,
                $email,
                $doc,
            );
            $method = Payment::METHOD_CARD;
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'method' => $method,
            'status' => $result->status,
            'amount_cents' => $order->total_cents,
            'gateway_payment_id' => $result->gatewayPaymentId,
            'pix_qr_base64' => $result->pixQrBase64,
            'pix_copy_paste' => $result->pixCopyPaste,
            'pix_expires_at' => $result->pixExpiresAt,
            'payload' => $result->raw,
        ]);

        // Pix pendente: estende o hold dos assentos até a expiração do Pix,
        // para o lugar não vencer (hold padrão = 10min) antes do pagamento (Pix ~30min).
        if ($method === Payment::METHOD_PIX && $result->isPending() && $result->pixExpiresAt !== null) {
            $this->extendHold($reservation, $result->pixExpiresAt);
        }

        $this->sync($payment, $result);

        return $order->fresh() ?? $order;
    }

    /** Cancela um pedido pendente (Pix vencido) e devolve os assentos. */
    public function expirePending(Payment $payment): void
    {
        $order = $payment->order;
        if ($order->status !== Order::STATUS_PENDING) {
            return;
        }

        DB::transaction(function () use ($order, $payment): void {
            $payment->update(['status' => Payment::STATUS_CANCELLED]);
            $order->update(['status' => Order::STATUS_CANCELLED]);
            $order->loadMissing('reservation');
            if ($order->reservation !== null) {
                $this->reservations->release($order->reservation);
            }
        });
    }

    private function extendHold(Reservation $reservation, CarbonInterface $until): void
    {
        $buffered = $until->copy()->addMinutes(5);

        $reservation->update(['expires_at' => $buffered]);
        SessionSeat::query()
            ->where('held_by_reservation_id', $reservation->id)
            ->where('status', SessionSeat::STATUS_HELD)
            ->update(['hold_expires_at' => $buffered]);
    }

    /** Reconcilia um pagamento existente com o resultado do gateway (webhook/polling). */
    public function sync(Payment $payment, PaymentResult $result): void
    {
        $payment->update([
            'status' => $result->status,
            'gateway_payment_id' => $result->gatewayPaymentId !== '' ? $result->gatewayPaymentId : $payment->gateway_payment_id,
            'pix_qr_base64' => $result->pixQrBase64 ?? $payment->pix_qr_base64,
            'pix_copy_paste' => $result->pixCopyPaste ?? $payment->pix_copy_paste,
            'payload' => $result->raw !== [] ? $result->raw : $payment->payload,
        ]);

        $order = $payment->order;

        if ($result->isApproved()) {
            $this->issuance->issueForOrder($order);

            return;
        }

        if (! $result->isPending() && $order->status !== Order::STATUS_PAID) {
            $order->update(['status' => Order::STATUS_FAILED]);
        }
    }
}
