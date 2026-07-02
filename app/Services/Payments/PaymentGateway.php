<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Porta do gateway de pagamento — implementada pelo MercadoPagoGateway. */
interface PaymentGateway
{
    /** Cobra no cartão usando um token tokenizado no cliente (Bricks). */
    public function chargeCard(
        Order $order,
        string $cardToken,
        int $installments,
        ?string $paymentMethodId,
        string $payerEmail,
        ?string $payerDoc,
    ): PaymentResult;

    /** Cria um pagamento Pix; retorna QR + copia-e-cola. */
    public function createPix(Order $order, string $payerEmail, ?string $payerDoc): PaymentResult;

    /** Consulta o pagamento no gateway (fonte da verdade no webhook). */
    public function fetchPayment(string $gatewayPaymentId): PaymentResult;

    /** Estorna (total se $amountCents for null). */
    public function refund(string $gatewayPaymentId, ?int $amountCents = null): bool;
}
