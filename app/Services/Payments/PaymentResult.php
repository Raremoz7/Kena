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
}
