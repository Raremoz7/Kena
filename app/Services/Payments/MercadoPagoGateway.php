<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Support\MercadoPagoSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Adapter do Mercado Pago (API /v1/payments). Cartão tokenizado no cliente,
 * Pix com QR/copia-e-cola. Nunca recebe número de cartão — só o token.
 */
class MercadoPagoGateway implements PaymentGateway
{
    public function chargeCard(
        Order $order,
        string $cardToken,
        int $installments,
        ?string $paymentMethodId,
        string $payerEmail,
        ?string $payerDoc,
    ): PaymentResult {
        $body = [
            'transaction_amount' => $this->amount($order),
            'token' => $cardToken,
            'installments' => max(1, $installments),
            'description' => 'Kena · '.$order->reference,
            'statement_descriptor' => MercadoPagoSettings::statementDescriptor(),
            'external_reference' => $order->reference,
            'payer' => $this->payer($payerEmail, $payerDoc),
        ];
        if ($paymentMethodId !== null && $paymentMethodId !== '') {
            $body['payment_method_id'] = $paymentMethodId;
        }

        $data = $this->post('/v1/payments', $body, $order->reference.'-card');

        return $this->toResult($data, 'card');
    }

    public function createPix(Order $order, string $payerEmail, ?string $payerDoc): PaymentResult
    {
        $minutes = MercadoPagoSettings::pixExpirationMinutes();

        $body = [
            'transaction_amount' => $this->amount($order),
            'description' => 'Kena · '.$order->reference,
            'payment_method_id' => 'pix',
            'external_reference' => $order->reference,
            'date_of_expiration' => now()->addMinutes($minutes)->toIso8601String(),
            'payer' => $this->payer($payerEmail, $payerDoc),
        ];

        $data = $this->post('/v1/payments', $body, $order->reference.'-pix');

        return $this->toResult($data, 'pix');
    }

    public function fetchPayment(string $gatewayPaymentId): PaymentResult
    {
        $response = $this->client()->get('/v1/payments/'.$gatewayPaymentId);
        if ($response->failed()) {
            throw new PaymentException('Falha ao consultar pagamento no Mercado Pago: '.$response->status());
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $method = ($data['payment_type_id'] ?? null) === 'bank_transfer' ? 'pix' : 'card';

        return $this->toResult($data, $method);
    }

    public function refund(string $gatewayPaymentId, ?int $amountCents = null): bool
    {
        $body = $amountCents !== null ? ['amount' => round($amountCents / 100, 2)] : [];

        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => 'refund-'.$gatewayPaymentId.'-'.($amountCents ?? 'full')])
            ->post('/v1/payments/'.$gatewayPaymentId.'/refunds', $body);

        return $response->successful();
    }

    public function cancelPayment(string $gatewayPaymentId): bool
    {
        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => 'cancel-'.$gatewayPaymentId])
            ->put('/v1/payments/'.$gatewayPaymentId, ['status' => 'cancelled']);

        return $response->successful();
    }

    private function amount(Order $order): float
    {
        return round($order->total_cents / 100, 2);
    }

    /** @return array<string, mixed> */
    private function payer(string $email, ?string $doc): array
    {
        $payer = ['email' => $email];
        if ($doc !== null && $doc !== '') {
            $payer['identification'] = ['type' => 'CPF', 'number' => preg_replace('/\D/', '', $doc)];
        }

        return $payer;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body, string $idempotencyKey): array
    {
        $response = $this->client()
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post($path, $body);

        if ($response->failed()) {
            // Loga o erro cru do MP (cause + message) pra diagnóstico, sem
            // expor detalhe técnico ao comprador.
            Log::warning('Mercado Pago recusou a requisição.', [
                'path' => $path,
                'status' => $response->status(),
                'error' => $response->json('error'),
                'message' => $response->json('message'),
                'cause' => $response->json('cause'),
            ]);

            throw new PaymentException($this->friendlyApiError($response->json()));
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    /** @param array<string, mixed> $data */
    private function toResult(array $data, string $method): PaymentResult
    {
        $poi = $data['point_of_interaction']['transaction_data'] ?? [];
        $expiration = $data['date_of_expiration'] ?? null;

        return new PaymentResult(
            gatewayPaymentId: (string) ($data['id'] ?? ''),
            status: PaymentResult::normalizeStatus((string) ($data['status'] ?? 'rejected')),
            method: $method,
            raw: $data,
            pixQrBase64: is_array($poi) ? ($poi['qr_code_base64'] ?? null) : null,
            pixCopyPaste: is_array($poi) ? ($poi['qr_code'] ?? null) : null,
            pixExpiresAt: $expiration !== null ? Carbon::parse((string) $expiration) : null,
            statusDetail: isset($data['status_detail']) ? (string) $data['status_detail'] : null,
        );
    }

    /**
     * Converte o erro de API do Mercado Pago (4xx) numa mensagem clara pro
     * comprador, mapeando as causas mais comuns.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function friendlyApiError(?array $body): string
    {
        $causeCode = (string) ($body['cause'][0]['code'] ?? '');

        // Erros de validação comuns na criação do pagamento.
        return match ($causeCode) {
            '2067', '3000', '3001', '3003' => 'Confira os dados do cartão e tente novamente.',
            '2062', '2063', '2060' => 'Informe um CPF válido para concluir o pagamento.',
            '4037', '4038' => 'Valor do pagamento inválido.',
            default => 'Não foi possível processar o pagamento. Confira os dados e tente novamente.',
        };
    }

    private function client(): PendingRequest
    {
        $token = MercadoPagoSettings::accessToken();
        if ($token === null || $token === '') {
            throw new PaymentException('Access token do Mercado Pago não configurado (painel ou .env).');
        }

        return Http::baseUrl(MercadoPagoSettings::baseUrl())
            ->withToken($token)
            ->acceptJson()
            ->timeout(20);
    }
}
