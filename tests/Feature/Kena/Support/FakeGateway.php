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

    /** Quantas cobranças de cartão foram feitas no gateway (espião). */
    public int $cardCharges = 0;

    public function chargeCard(
        Order $order,
        string $cardToken,
        int $installments,
        ?string $paymentMethodId,
        string $payerEmail,
        ?string $payerDoc,
    ): PaymentResult {
        $this->cardCharges++;

        return new PaymentResult('FAKE-'.$order->id, $this->chargeStatus, 'card', ['id' => 'FAKE-'.$order->id]);
    }

    /** Quantos Pix foram criados no gateway (espião). */
    public int $pixCreated = 0;

    /** @var list<string> IDs cancelados no gateway (espião). */
    public array $cancellations = [];

    public function cancelPayment(string $gatewayPaymentId): bool
    {
        $this->cancellations[] = $gatewayPaymentId;

        return true;
    }

    public function createPix(Order $order, string $payerEmail, ?string $payerDoc): PaymentResult
    {
        $this->pixCreated++;

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

    /** @var list<string> IDs estornados (espião para asserções). */
    public array $refunds = [];

    /** Simula recusa de estorno pelo gateway quando false. */
    public bool $refundOk = true;

    public function refund(string $gatewayPaymentId, ?int $amountCents = null): bool
    {
        $this->refunds[] = $gatewayPaymentId;

        return $this->refundOk;
    }
}
