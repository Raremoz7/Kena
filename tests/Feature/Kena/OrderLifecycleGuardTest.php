<?php

namespace Tests\Feature\Kena;

use App\Exceptions\PaymentException;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Guardas do ciclo do pedido: expiração × pagamento, pedido gratuito, sessão cancelada, refresh do Pix. */
class OrderLifecycleGuardTest extends TestCase
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

    public function test_expire_pending_with_stale_snapshot_does_not_cancel_paid_order(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-STALE',
        ]);

        // O comando de reconciliação carregou o payment/order ANTES...
        $stalePayment = Payment::with('order')->findOrFail($payment->id);

        // ...e o webhook aprovou no meio do caminho (pedido PAGO, ingressos emitidos).
        app(PaymentService::class)->sync(
            Payment::findOrFail($payment->id),
            new PaymentResult('MP-STALE', PaymentResult::STATUS_APPROVED, 'pix', ['id' => 'MP-STALE']),
        );
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);

        // O expire com snapshot velho NÃO pode derrubar o pedido pago.
        app(PaymentService::class)->expirePending($stalePayment);

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status, 'pedido pago não pode virar cancelado');
        $this->assertSame(1, Ticket::where('order_id', $order->id)->where('status', Ticket::STATUS_VALID)->count());
        $this->assertSame(Payment::STATUS_APPROVED, $payment->refresh()->status);
    }

    public function test_free_order_is_issued_without_touching_the_gateway(): void
    {
        $session = $this->makeSession(1, 4500);
        Coupon::create([
            'code' => 'CORTESIA', 'type' => Coupon::TYPE_PERCENT, 'value' => 100,
            'max_uses' => null, 'used' => 0, 'active' => true,
        ]);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        $order = app(PaymentService::class)->pay($reservation, [
            'method' => 'card', 'token' => 'tok', 'coupon_code' => 'CORTESIA',
        ]);

        $this->assertSame(0, $order->total_cents);
        $this->assertSame(Order::STATUS_PAID, $order->status, 'pedido gratuito deve ser emitido direto');
        $this->assertSame(1, Ticket::where('order_id', $order->id)->count());
        $this->assertSame(0, $this->gateway->cardCharges, 'gateway não pode ser chamado com valor zero');
        $this->assertSame(0, $this->gateway->pixCreated);
    }

    public function test_paying_an_order_of_a_cancelled_session_is_rejected(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        // Organizador cancela a sessão com o checkout aberto.
        $session->update(['status' => 'cancelled']);

        $this->expectException(PaymentException::class);
        app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok']);
    }

    public function test_checkout_page_restores_pending_pix_after_refresh(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        app(PaymentService::class)->pay($reservation, ['method' => 'pix']);

        $this->actingAs($user)
            ->get(route('checkout', $reservation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('buyer/checkout')
                ->has('pendingPayment.pix.copyPaste')
                ->has('pendingPayment.reservationExpiresAt'));
    }
}
