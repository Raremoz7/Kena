<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\Log;

/** Processamento idempotente de webhooks do Mercado Pago. */
class WebhookService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentService $payments,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handleMercadoPago(array $payload): void
    {
        $type = $payload['type'] ?? $payload['topic'] ?? null;
        $paymentId = $this->extractPaymentId($payload);
        $eventId = (string) ($payload['id'] ?? $paymentId);

        if ($eventId === '') {
            return;
        }

        /** @var WebhookEvent $record */
        $record = WebhookEvent::firstOrCreate(
            ['gateway' => 'mercadopago', 'gateway_event_id' => $eventId],
            ['type' => is_string($type) ? $type : null, 'payload' => $payload],
        );

        if ($record->processed_at !== null) {
            return; // já processado
        }

        if ($paymentId !== '' && ($type === 'payment' || $type === null)) {
            // Se o Payment ainda não existe (webhook chegou durante o pay()),
            // NÃO marca como processado — a reentrega do MP reprocessa depois.
            if (! $this->reconcile($paymentId)) {
                return;
            }
        }

        $record->update(['processed_at' => now()]);
    }

    private function reconcile(string $gatewayPaymentId): bool
    {
        $payment = Payment::where('gateway_payment_id', $gatewayPaymentId)->first();
        if ($payment === null) {
            return false;
        }

        try {
            $result = $this->gateway->fetchPayment($gatewayPaymentId);
        } catch (\Throwable $e) {
            Log::warning('Falha ao reconciliar pagamento MP', ['id' => $gatewayPaymentId, 'erro' => $e->getMessage()]);

            // Erro transitório na consulta: deixa reprocessar na reentrega.
            return false;
        }

        $this->payments->sync($payment, $result);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function extractPaymentId(array $payload): string
    {
        if (isset($payload['data']['id'])) {
            return (string) $payload['data']['id'];
        }
        if (isset($payload['resource']) && is_string($payload['resource'])) {
            return (string) preg_replace('/\D/', '', $payload['resource']);
        }

        return '';
    }
}
