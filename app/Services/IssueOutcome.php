<?php

namespace App\Services;

/** Resultado da tentativa de emissão de ingressos para um pedido. */
enum IssueOutcome: string
{
    /** Ingressos emitidos agora. */
    case Issued = 'issued';

    /** Pedido já estava pago e com ingressos (idempotente). */
    case AlreadyIssued = 'already_issued';

    /** Pedido não está mais em estado pagável (cancelado/reembolsado/falho). */
    case NotPayable = 'not_payable';

    /** Assentos do pedido foram tomados por outra compra (hold venceu). */
    case SeatsUnavailable = 'seats_unavailable';

    /** A aprovação chegou tarde demais — o dinheiro deve ser estornado. */
    public function requiresRefund(): bool
    {
        return $this === self::NotPayable || $this === self::SeatsUnavailable;
    }
}
