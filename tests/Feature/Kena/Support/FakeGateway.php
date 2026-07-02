<?php

namespace Tests\Feature\Kena\Support;

use App\Models\Order;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;

/** Gateway de pagamento fake para testes — sem rede. */
class FakeGateway implements PaymentGateway
{
    public string $chargeStatus = PaymentResult::STATUS_APPROVED;

    public string $fetchStatus = PaymentResult::STATUS_APPROVED;

    public function chargeCard(
        Order $order,
        string $cardToken,
        int $installments,
        ?string $paymentMethodId,
        string $payerEmail,
        ?string $payerDoc,
    ): PaymentResult {
        return new PaymentResult('FAKE-'.$order->id, $this->chargeStatus, 'card', ['id' => 'FAKE-'.$order->id]);
    }

    public function createPix(Order $order, string $payerEmail, ?string $payerDoc): PaymentResult
    {
        return new PaymentResult(
            'FAKE-PIX-'.$order->id,
            PaymentResult::STATUS_PENDING,
            'pix',
            ['id' => 'FAKE-PIX-'.$order->id],
            pixQrBase64: 'cXItYmFzZTY0',
            pixCopyPaste: '00020126-fake-pix',
            pixExpiresAt: now()->addMinutes(30),
        );
    }

    public function fetchPayment(string $gatewayPaymentId): PaymentResult
    {
        return new PaymentResult($gatewayPaymentId, $this->fetchStatus, 'pix', ['id' => $gatewayPaymentId]);
    }

    public function refund(string $gatewayPaymentId, ?int $amountCents = null): bool
    {
        return true;
    }
}
