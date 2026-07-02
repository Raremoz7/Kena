<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Reservation;
use App\Support\Money;

/**
 * Cálculo de preço do pedido a partir de uma reserva. Tudo em centavos;
 * a taxa de serviço incide sobre o subtotal já com desconto.
 *
 * @phpstan-type Quote array{subtotal_cents: int, discount_cents: int, fee_cents: int, total_cents: int, lines: array<int, array{label: string, value: float, tone: string}>, total: float}
 */
class PricingService
{
    /** @return Quote */
    public function quoteForReservation(Reservation $reservation, ?Coupon $coupon = null): array
    {
        $reservation->loadMissing(['seats.sessionSeat.sector']);

        $subtotal = (int) $reservation->seats->sum('price_cents');
        $discount = $coupon !== null ? $coupon->discountFor($subtotal) : 0;
        $fee = $this->fee($subtotal - $discount);
        $total = $subtotal - $discount + $fee;

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
                'label' => 'Cupom '.$coupon->code,
                'value' => -Money::toReais($discount),
                'tone' => 'success',
            ];
        }
        if ($fee > 0) {
            $lines[] = [
                'label' => 'Taxa de serviço',
                'value' => Money::toReais($fee),
                'tone' => 'muted',
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

    private function fee(int $base): int
    {
        $percent = (float) config('kena.service_fee_percent', 10);

        return (int) round($base * $percent / 100);
    }
}
