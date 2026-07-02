<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

class PaymentReconciliationTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kena.mercadopago.access_token' => 'TEST-TOKEN']);
    }

    public function test_reconcile_confirms_pix_the_webhook_missed(): void
    {
        $fake = new FakeGateway;
        $fake->fetchStatus = PaymentResult::STATUS_APPROVED;
        $this->app->instance(PaymentGateway::class, $fake);

        $payment = $this->pendingPixPayment();

        $this->artisan('kena:reconcile-payments')->assertExitCode(0);

        $order = $payment->order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(1, $order->tickets()->count());
        $this->assertSame(SessionSeat::STATUS_SOLD, SessionSeat::first()->status);
    }

    public function test_reconcile_expires_overdue_pix_and_frees_seats(): void
    {
        $fake = new FakeGateway;
        $fake->fetchStatus = PaymentResult::STATUS_PENDING; // continua pendente no gateway
        $this->app->instance(PaymentGateway::class, $fake);

        $payment = $this->pendingPixPayment();
        $payment->update(['pix_expires_at' => now()->subMinute()]);

        $this->artisan('kena:reconcile-payments')->assertExitCode(0);

        $order = $payment->order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::first()->status);
        $this->assertSame(Reservation::STATUS_CANCELLED, $order->reservation->refresh()->status);
    }

    public function test_pix_extends_seat_hold_until_expiration(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);

        $session = $this->makeSession(1);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        app(PaymentService::class)->pay($reservation, ['method' => 'pix']);

        // hold padrão é 10min; com Pix deve ir bem além (≈ 30min + buffer).
        $this->assertTrue($reservation->refresh()->expires_at->greaterThan(now()->addMinutes(20)));
        $this->assertTrue(SessionSeat::first()->hold_expires_at->greaterThan(now()->addMinutes(20)));
    }

    private function pendingPixPayment(): Payment
    {
        $session = $this->makeSession(1);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);

        return Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-'.$order->id,
            'pix_expires_at' => now()->addMinutes(30),
        ]);
    }
}
