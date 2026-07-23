<?php

namespace App\Services\Payments;

use Carbon\CarbonInterface;

/** Resultado normalizado de uma operação no gateway de pagamento. */
final class PaymentResult
{
    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $gatewayPaymentId,
        public readonly string $status,
        public readonly string $method,
        public readonly array $raw = [],
        public readonly ?string $pixQrBase64 = null,
        public readonly ?string $pixCopyPaste = null,
        public readonly ?CarbonInterface $pixExpiresAt = null,
        /** Detalhe do status do Mercado Pago (ex.: cc_rejected_insufficient_amount). */
        public readonly ?string $statusDetail = null,
    ) {}

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Normaliza o status do Mercado Pago para o nosso vocabulário. */
    public static function normalizeStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved', 'authorized' => self::STATUS_APPROVED,
            'pending', 'in_process', 'in_mediation' => self::STATUS_PENDING,
            'refunded', 'charged_back' => self::STATUS_REFUNDED,
            'cancelled', 'expired' => self::STATUS_CANCELLED,
            default => self::STATUS_REJECTED,
        };
    }

    /**
     * Traduz o status_detail do Mercado Pago numa mensagem clara pro comprador.
     * Códigos: https://www.mercadopago.com.br/developers/pt/docs/checkout-api/response-handling/collection-results
     */
    public static function friendlyStatusDetail(?string $detail): ?string
    {
        if ($detail === null || $detail === '') {
            return null;
        }

        return match ($detail) {
            'cc_rejected_bad_filled_card_number' => 'Confira o número do cartão.',
            'cc_rejected_bad_filled_date' => 'Confira a data de validade do cartão.',
            'cc_rejected_bad_filled_security_code' => 'Confira o código de segurança (CVV).',
            'cc_rejected_bad_filled_other' => 'Confira os dados do cartão.',
            'cc_rejected_insufficient_amount' => 'O cartão não tem saldo/limite suficiente.',
            'cc_rejected_call_for_authorize' => 'Autorize o pagamento com o banco emissor do cartão e tente de novo.',
            'cc_rejected_card_disabled' => 'O cartão está desativado. Fale com o banco emissor.',
            'cc_rejected_card_error' => 'Não foi possível processar o cartão. Tente outro.',
            'cc_rejected_duplicated_payment' => 'Este pagamento parece duplicado. Aguarde alguns minutos antes de tentar de novo.',
            'cc_rejected_high_risk' => 'O pagamento foi recusado por segurança. Use outro meio de pagamento.',
            'cc_rejected_max_attempts' => 'Muitas tentativas. Use outro cartão ou tente mais tarde.',
            'cc_rejected_invalid_installments' => 'O cartão não aceita esse número de parcelas.',
            'cc_rejected_blacklist' => 'O pagamento não pôde ser processado. Use outro meio de pagamento.',
            default => 'O pagamento não foi aprovado. Tente outro meio de pagamento.',
        };
    }
}
