<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;

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

    /** Registra o resgate e incrementa o uso. */
    public function redeem(Coupon $coupon, Order $order, int $discountCents): void
    {
        CouponRedemption::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'discount_cents' => $discountCents,
        ]);

        $coupon->increment('used');
    }
}
