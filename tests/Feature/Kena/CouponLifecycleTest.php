<?php

namespace Tests\Feature\Kena;

use App\Exceptions\CouponExhaustedException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Ciclo de vida do cupom: resgate atômico, devolução em falha e troca pós-Pix. */
class CouponLifecycleTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function makeCoupon(int $maxUses = 1): Coupon
    {
        return Coupon::create([
            'code' => 'TESTE10',
            'type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'max_uses' => $maxUses,
            'used' => 0,
            'active' => true,
        ]);
    }

    public function test_failed_payment_releases_the_coupon_use(): void
    {
        $session = $this->makeSession(2, 4500);
        $coupon = $this->makeCoupon(maxUses: 1);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        // Cartão recusado: pedido falha e o uso do cupom volta.
        $this->gateway->chargeStatus = PaymentResult::STATUS_REJECTED;
        $order = app(PaymentService::class)->pay($reservation, [
            'method' => 'card', 'token' => 'tok', 'coupon_code' => 'TESTE10',
        ]);

        $this->assertSame(Order::STATUS_FAILED, $order->refresh()->status);
        $this->assertSame(0, $coupon->refresh()->used, 'uso do cupom deve ser devolvido no pagamento falho');
        $this->assertSame(0, CouponRedemption::count());

        // Retry aprovado: cupom aplica normalmente (1 uso por 1 venda).
        $this->gateway->chargeStatus = PaymentResult::STATUS_APPROVED;
        $retry = app(PaymentService::class)->pay($reservation->refresh(), [
            'method' => 'card', 'token' => 'tok', 'coupon_code' => 'TESTE10',
        ]);

        $this->assertSame(Order::STATUS_PAID, $retry->status);
        $this->assertGreaterThan(0, $retry->discount_cents);
        $this->assertSame(1, $coupon->refresh()->used);
    }

    public function test_last_coupon_use_cannot_be_redeemed_twice(): void
    {
        $session = $this->makeSession(2, 4500);
        $this->makeCoupon(maxUses: 1);
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $seats = $this->availableSeatIds($session, 2);
        $reservationA = app(SeatReservationService::class)->hold($session, $userA, [$seats[0]]);
        $reservationB = app(SeatReservationService::class)->hold($session, $userB, [$seats[1]]);

        // Dois checkouts carregam o cupom como válido ANTES de qualquer resgate.
        $couponForA = app(CouponService::class)->resolve('TESTE10', $session->event_id);
        $couponForB = app(CouponService::class)->resolve('TESTE10', $session->event_id);
        $this->assertNotNull($couponForA);
        $this->assertNotNull($couponForB);

        app(OrderService::class)->createFromReservation($reservationA, $couponForA);

        $this->expectException(CouponExhaustedException::class);
        app(OrderService::class)->createFromReservation($reservationB, $couponForB);
    }

    public function test_changing_coupon_replaces_pending_order_and_cancels_old_pix(): void
    {
        $session = $this->makeSession(2, 4500);
        $this->makeCoupon(maxUses: 10);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        // Pix gerado SEM cupom.
        $first = app(PaymentService::class)->pay($reservation, ['method' => 'pix']);
        $pix = Payment::where('order_id', $first->id)->firstOrFail();
        $this->assertSame(0, $first->discount_cents);

        // Usuário aplica o cupom e paga com cartão: o total COBRADO tem que ser o com desconto.
        $paid = app(PaymentService::class)->pay($reservation->refresh(), [
            'method' => 'card', 'token' => 'tok', 'coupon_code' => 'TESTE10',
        ]);

        $this->assertSame(Order::STATUS_PAID, $paid->status);
        $this->assertGreaterThan(0, $paid->discount_cents, 'o pedido cobrado deve refletir o cupom aplicado');
        $this->assertNotSame($first->id, $paid->id, 'pedido pendente com cupom diferente deve ser substituído');
        $this->assertSame(Order::STATUS_CANCELLED, $first->refresh()->status);
        $this->assertContains($pix->gateway_payment_id, $this->gateway->cancellations, 'Pix do pedido substituído deve morrer no gateway');
    }

    public function test_repeating_pix_with_same_coupon_still_reuses_payment(): void
    {
        $session = $this->makeSession(2, 4500);
        $this->makeCoupon(maxUses: 10);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => 'TESTE10']);
        app(PaymentService::class)->pay($reservation->refresh(), ['method' => 'pix', 'coupon_code' => 'TESTE10']);

        $this->assertSame(1, $this->gateway->pixCreated);
        $this->assertSame(1, Order::count());
    }
}
