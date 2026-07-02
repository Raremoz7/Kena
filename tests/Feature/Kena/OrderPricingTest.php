<?php

namespace Tests\Feature\Kena;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class OrderPricingTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_order_totals_apply_coupon_and_service_fee(): void
    {
        config(['kena.service_fee_percent' => 10]);

        $session = $this->makeSession(3, 4500);
        $user = User::factory()->create();
        Coupon::create(['code' => 'NOITE10', 'type' => Coupon::TYPE_PERCENT, 'value' => 10, 'active' => true]);

        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 3));

        $coupon = app(CouponService::class)->resolve('NOITE10', $session->event_id);
        $order = app(OrderService::class)->createFromReservation($reservation, $coupon);

        // 3 × 4500 = 13500 | desconto 10% = 1350 | taxa 10% sobre 12150 = 1215 | total 13365
        $this->assertSame(13500, $order->subtotal_cents);
        $this->assertSame(1350, $order->discount_cents);
        $this->assertSame(1215, $order->fee_cents);
        $this->assertSame(13365, $order->total_cents);
        $this->assertCount(3, $order->items);

        $this->assertSame(1, Coupon::where('code', 'NOITE10')->first()->used);
        $this->assertSame(1, CouponRedemption::where('order_id', $order->id)->count());
    }

    public function test_creating_order_twice_is_idempotent(): void
    {
        $session = $this->makeSession(2, 4500);
        $user = User::factory()->create();

        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 2));

        $service = app(OrderService::class);
        $a = $service->createFromReservation($reservation);
        $b = $service->createFromReservation($reservation);

        $this->assertSame($a->id, $b->id);
    }
}
