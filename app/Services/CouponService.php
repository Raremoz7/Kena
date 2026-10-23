<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Reservation;

/** Validação e resgate de cupons. */
class CouponService
{
    /** Cupom resgatável pelo código para um dado evento, ou null. */
    public function resolve(?string $code, ?int $eventId = null): ?Coupon
    {
        $code = $code !== null ? trim(mb_strtoupper($code)) : null;
        if ($code === null || $code === '') {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();
        if ($coupon === null || ! $coupon->isRedeemable($eventId)) {
            return null;
        }

        return $coupon;
    }

    /**
     * Como `resolve`, mas reconhece o cupom que o pedido pendente desta reserva
     * já resgatou. Sem isso, refazer o pagamento (cartão recusado, trocar Pix por
     * cartão) achava o código de uso único "esgotado" pelo próprio pedido e
     * recriava a compra a preço cheio — a cortesia sumia.
     */
    public function resolveForReservation(?string $code, Reservation $reservation): ?Coupon
    {
        $normalized = $code !== null ? trim(mb_strtoupper($code)) : '';

        if ($normalized !== '') {
            $redeemed = Coupon::query()
                ->whereIn('id', Order::where('reservation_id', $reservation->id)
                    ->where('status', Order::STATUS_PENDING)
                    ->select('coupon_id'))
                ->where('code', $normalized)
                ->first();

            if ($redeemed !== null && $redeemed->active) {
                return $redeemed;
            }
        }

        return $this->resolve($code, $reservation->session->event_id);
    }

    /**
     * Registra o resgate com incremento ATÔMICO: o `used` só sobe se ainda há
     * uso disponível — duas pessoas disputando o último uso, só uma leva.
     */
    public function redeem(Coupon $coupon, Order $order, int $discountCents): bool
    {
        $claimed = Coupon::whereKey($coupon->id)
            ->where(function ($query): void {
                $query->whereNull('max_uses')->orWhereColumn('used', '<', 'max_uses');
            })
            ->increment('used');

        if ($claimed === 0) {
            return false;
        }

        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'discount_cents' => $discountCents,
        ]);

        return true;
    }

    /** Devolve o uso quando o pedido morre sem virar venda (falha/cancelamento). */
    public function release(Order $order): void
    {
        $redemption = CouponRedemption::where('order_id', $order->id)->first();
        if ($redemption === null) {
            return;
        }

        $redemption->delete();
        Coupon::whereKey($redemption->coupon_id)
            ->where('used', '>', 0)
            ->decrement('used');
    }
}
