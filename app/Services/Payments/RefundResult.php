<?php

namespace App\Services\Payments;

/** Resultado de um estorno no gateway: se foi aceito e o id que o gateway deu a ele. */
final class RefundResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $gatewayRefundId = null,
    ) {}
}
