<?php

namespace App\Services;

use App\Exceptions\CouponExhaustedException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Support\Codes;
use Illuminate\Support\Facades\DB;

/** Cria pedidos a partir de uma reserva, com snapshot de itens e totais. */
class OrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CouponService $coupons,
    ) {}

    /** Idempotente por reserva: reusa o pedido pendente existente, se houver. */
    public function createFromReservation(Reservation $reservation, ?Coupon $coupon = null): Order
    {
        return DB::transaction(function () use ($reservation, $coupon): Order {
            $reservation->loadMissing(['seats.sessionSeat.seat', 'seats.sessionSeat.sector']);

            $existing = Order::where('reservation_id', $reservation->id)
                ->where('status', Order::STATUS_PENDING)
                ->first();
            if ($existing !== null) {
                if ($existing->coupon_id === $coupon?->id) {
                    return $existing;
                }

                // Cupom mudou desde o pedido pendente: o total antigo não vale
                // mais — cancela o pedido (devolvendo o resgate) e recria.
                $existing->update(['status' => Order::STATUS_CANCELLED]);
                $this->coupons->release($existing);
            }

            $quote = $this->pricing->quoteForReservation($reservation, $coupon);

            $order = Order::create([
                'user_id' => $reservation->user_id,
                'session_id' => $reservation->session_id,
                'reservation_id' => $reservation->id,
                'coupon_id' => $coupon?->id,
                'reference' => Codes::orderReference(),
                'subtotal_cents' => $quote['subtotal_cents'],
                'discount_cents' => $quote['discount_cents'],
                'fee_cents' => $quote['fee_cents'],
                'total_cents' => $quote['total_cents'],
                'status' => Order::STATUS_PENDING,
            ]);

            foreach ($reservation->seats as $rs) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'session_seat_id' => $rs->session_seat_id,
                    'seat_code' => $rs->sessionSeat->seat->code,
                    'sector_name' => $rs->sessionSeat->sector->name,
                    'price_cents' => $rs->price_cents,
                ]);
            }

            if ($coupon !== null && $quote['discount_cents'] > 0
                && ! $this->coupons->redeem($coupon, $order, $quote['discount_cents'])) {
                // Rollback do pedido inteiro: o desconto já estava no total.
                throw new CouponExhaustedException;
            }

            return $order;
        });
    }
}
