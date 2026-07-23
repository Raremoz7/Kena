<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Mail\OrderCancelledMail;
use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * @param  array{method?: string, token?: string, installments?: int, payment_method_id?: string, coupon_code?: string, document?: string}  $input
     */
    public function pay(Reservation $reservation, array $input): Order
    {
        // Sessão cancelada com checkout aberto não pode mais receber pagamento.
        $reservation->loadMissing('session');
        if ($reservation->session->status === 'cancelled') {
            throw new PaymentException('Esta sessão foi cancelada e não aceita mais pagamentos.');
        }

        $coupon = $this->coupons->resolve($input['coupon_code'] ?? null, $reservation->session->event_id);
        $order = $this->orders->createFromReservation($reservation, $coupon);

        if ($order->status === Order::STATUS_PAID) {
            return $order;
        }

        // Pedido gratuito (cupom 100%): não há o que cobrar — emite direto.
        if ($order->total_cents === 0) {
            Payment::create([
                'order_id' => $order->id,
                'gateway' => 'kena',
                'method' => Payment::METHOD_FREE,
                'status' => Payment::STATUS_APPROVED,
                'amount_cents' => 0,
            ]);
            $this->issuance->issueForOrder($order);

            return $order->fresh() ?? $order;
        }

        $order->loadMissing('user');
        $email = (string) $order->user->email;
        $doc = $this->resolvePayerDocument($order, $input['document'] ?? null);

        $method = ($input['method'] ?? 'card') === Payment::METHOD_PIX
            ? Payment::METHOD_PIX
            : Payment::METHOD_CARD;

        // Um comprador não pode acumular dois meios de pagamento vivos —
        // inclui pagamentos de pedidos substituídos (ex.: cupom mudou).
        $pendingPayments = Payment::query()
            ->whereIn('order_id', Order::where('reservation_id', $reservation->id)->select('id'))
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_payment_id')
            ->get();

        $reusablePix = $method === Payment::METHOD_PIX
            ? $pendingPayments->first(fn (Payment $p): bool => $p->method === Payment::METHOD_PIX && $p->order_id === $order->id)
            : null;

        foreach ($pendingPayments as $pending) {
            if ($reusablePix !== null && $pending->id === $reusablePix->id) {
                continue;
            }

            // Mata o pagamento antigo no gateway — senão o QR/cobrança antiga
            // continua pagável e o cliente pode pagar em dobro.
            $this->gateway->cancelPayment((string) $pending->gateway_payment_id);
            $pending->update(['status' => Payment::STATUS_CANCELLED]);
        }

        // Pix repetido pro mesmo pedido: reusa o QR pendente.
        if ($reusablePix !== null) {
            return $order->fresh() ?? $order;
        }

        if ($method === Payment::METHOD_PIX) {
            $result = $this->gateway->createPix($order, $email, $doc);
        } else {
            $result = $this->gateway->chargeCard(
                $order,
                (string) ($input['token'] ?? ''),
                (int) ($input['installments'] ?? 1),
                $input['payment_method_id'] ?? null,
                $email,
                $doc,
            );
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
        $expired = DB::transaction(function () use ($payment): bool {
            // Estado atual sob lock — o snapshot do chamador pode estar velho
            // (o webhook pode ter aprovado o pedido no meio do caminho).
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            if ($order->status !== Order::STATUS_PENDING) {
                return false;
            }

            Payment::whereKey($payment->id)
                ->where('status', Payment::STATUS_PENDING)
                ->update(['status' => Payment::STATUS_CANCELLED]);
            $order->update(['status' => Order::STATUS_CANCELLED]);
            $this->coupons->release($order);
            $order->loadMissing('reservation');
            if ($order->reservation !== null) {
                $this->reservations->release($order->reservation);
            }

            return true;
        });

        // Avisa o comprador — sem isso um Pix abandonado morre em silêncio.
        if ($expired) {
            $order = $payment->order()->with('user')->first();
            if ($order !== null && filled($order->user->email)) {
                Mail::to($order->user->email)->queue(new OrderCancelledMail(
                    $order,
                    'O prazo de pagamento do Pix terminou e os assentos foram liberados. Se ainda quiser ir, faça uma nova compra.',
                ));
            }
        }
    }

    /**
     * CPF do pagador: usa o que já está no cadastro; se faltar e o comprador
     * informou um no checkout, persiste (o Mercado Pago exige CPF no cartão).
     */
    private function resolvePayerDocument(Order $order, ?string $informed): ?string
    {
        $existing = $order->user->cpf;
        if ($existing !== null && $existing !== '') {
            return $existing;
        }

        $digits = $informed !== null ? preg_replace('/\D/', '', $informed) : null;
        if ($digits === null || strlen($digits) !== 11) {
            return null;
        }

        // Não sobrescreve o CPF de outra conta (coluna única) — só grava se livre.
        $takenByOther = User::query()
            ->where('cpf', $digits)
            ->whereKeyNot($order->user->getKey())
            ->exists();
        if (! $takenByOther) {
            $order->user->forceFill(['cpf' => $digits])->save();
        }

        return $digits;
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
            $outcome = $this->issuance->issueForOrder($order);

            // Aprovação chegou tarde (pedido cancelado ou assento revendido):
            // não há o que entregar — estorna o pagamento automaticamente.
            if ($outcome->requiresRefund()) {
                $this->refundLateApproval($payment, $order);
            }

            return;
        }

        if (! $result->isPending() && $order->status !== Order::STATUS_PAID) {
            $order->update(['status' => Order::STATUS_FAILED]);
            // Pedido morreu sem virar venda: devolve o uso do cupom pro retry.
            $this->coupons->release($order);
        }
    }

    /** Estorna um pagamento aprovado que não pode mais ser entregue. */
    private function refundLateApproval(Payment $payment, Order $order): void
    {
        $gatewayOk = $payment->gateway_payment_id !== null
            && $this->gateway->refund($payment->gateway_payment_id);

        Refund::create([
            'order_id' => $order->id,
            'amount_cents' => $payment->amount_cents,
            'reason' => 'Pagamento aprovado após a expiração da reserva — estorno automático.',
            'status' => $gatewayOk ? Refund::STATUS_DONE : Refund::STATUS_FAILED,
            'gateway_refund_id' => null,
        ]);

        if (! $gatewayOk) {
            Log::error('Estorno automático de aprovação atrasada falhou no gateway.', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'gateway_payment_id' => $payment->gateway_payment_id,
            ]);

            return;
        }

        $payment->update(['status' => Payment::STATUS_REFUNDED]);
        if ($order->refresh()->status === Order::STATUS_PENDING) {
            $order->update(['status' => Order::STATUS_CANCELLED]);
        }
        $this->coupons->release($order);

        $order->loadMissing('user');
        if (filled($order->user->email)) {
            Mail::to($order->user->email)->queue(new RefundConfirmedMail($order));
        }
    }
}
