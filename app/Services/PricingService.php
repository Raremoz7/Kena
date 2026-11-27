<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Organization;
use App\Models\Reservation;
use App\Support\Money;

/**
 * Cálculo de preço do pedido a partir de uma reserva. Tudo em centavos.
 *
 * A taxa da plataforma é INTERNA: o comprador paga só o preço dos ingressos
 * (com desconto); a taxa é a parte desse valor que fica com a plataforma
 * (application_fee no Mercado Pago) e sai do que o organizador recebe. Não
 * aparece nem soma no total do comprador.
 *
 * @phpstan-type Quote array{subtotal_cents: int, discount_cents: int, fee_cents: int, total_cents: int, lines: array<int, array{label: string, value: float, tone: string}>, total: float}
 */
class PricingService
{
    /** @return Quote */
    public function quoteForReservation(Reservation $reservation, ?Coupon $coupon = null): array
    {
        $reservation->loadMissing(['seats.sessionSeat.sector', 'session.event.organization']);

        $subtotal = (int) $reservation->seats->sum('price_cents');
        $discount = $coupon !== null
            ? $coupon->discountForPrices($reservation->seats->pluck('price_cents')->map(fn ($p): int => (int) $p)->all())
            : 0;
        $total = $subtotal - $discount;
        $fee = $this->fee($total, $reservation->session->event->organization);

        $lines = [];
        foreach ($reservation->seats->groupBy(fn ($rs): string => $rs->sessionSeat->sector->name) as $name => $items) {
            $lines[] = [
                'label' => count($items).' × '.$name,
                'value' => Money::toReais((int) $items->sum('price_cents')),
                'tone' => 'default',
            ];
        }
        if ($coupon !== null && $discount > 0) {
            $lines[] = [
                'label' => ($coupon->isCourtesy() ? 'Cortesia ' : 'Cupom ').$coupon->code,
                'value' => -Money::toReais($discount),
                'tone' => 'success',
            ];
        }

        return [
            'subtotal_cents' => $subtotal,
            'discount_cents' => $discount,
            'fee_cents' => $fee,
            'total_cents' => $total,
            'lines' => $lines,
            'total' => Money::toReais($total),
        ];
    }

    /** Taxa da organização dona do evento (exceção negociada) ou a padrão. */
    private function fee(int $base, ?Organization $organization): int
    {
        $percent = $organization !== null
            ? $organization->serviceFeePercent()
            : (float) config('kena.service_fee_percent', 6);

        return (int) round($base * $percent / 100);
    }
}
