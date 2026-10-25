<?php

namespace Tests\Feature\Kena;

use App\Models\CheckIn;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\SeatReservationService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Correções da revisão de código (pagamento, reserva, estorno, check-in, checkout). */
class ReviewFixesTest extends TestCase
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

    private function coupon(string $type = Coupon::TYPE_PERCENT, int $value = 10): Coupon
    {
        return Coupon::create([
            'code' => 'TESTE10', 'type' => $type, 'value' => $value,
            'max_uses' => 1, 'used' => 0, 'active' => true,
        ]);
    }

    public function test_webhook_of_the_replaced_pix_does_not_fail_the_card_under_review(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create(['cpf' => '52998224725']);
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));

        // Pix gerado, depois troca para cartão, que fica em análise.
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'document' => '52998224725']);
        $this->gateway->chargeStatus = PaymentResult::STATUS_PENDING;
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok']);
        $pix = Payment::where('method', Payment::METHOD_PIX)->firstOrFail();
        $this->assertSame(Payment::STATUS_CANCELLED, $pix->status);

        // Chega o webhook do Pix cancelado: o pedido do cartão segue aberto.
        $this->gateway->fetchStatus = PaymentResult::STATUS_CANCELLED;
        app(WebhookService::class)->handleMercadoPago(['type' => 'payment', 'id' => 'evt-pix', 'data' => ['id' => $pix->gateway_payment_id]]);
        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);

        // O cartão é aprovado: ingresso emitido, sem estorno.
        $this->gateway->fetchStatus = PaymentResult::STATUS_APPROVED;
        $card = Payment::where('method', Payment::METHOD_CARD)->firstOrFail();
        app(WebhookService::class)->handleMercadoPago(['type' => 'payment', 'id' => 'evt-card', 'data' => ['id' => $card->gateway_payment_id]]);

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertSame(1, $order->tickets()->count());
        $this->assertSame([], $this->gateway->refunds);
    }

    public function test_cancelling_the_reservation_kills_the_live_pix(): void
    {
        $session = $this->makeSession(2, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'document' => '52998224725']);

        $this->actingAs($user)->delete(route('reservations.release', $reservation))->assertRedirect();

        $pix = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame([$pix->gateway_payment_id], $this->gateway->cancellations);
        $this->assertSame(Payment::STATUS_CANCELLED, $pix->status);
        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
    }

    public function test_refunding_a_courtesy_frees_the_code_again(): void
    {
        $session = $this->makeSession(2, 4500);
        $coupon = $this->coupon(Coupon::TYPE_PERCENT, 100);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'free', 'coupon_code' => 'TESTE10']);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(1, $coupon->refresh()->used);

        app(RefundService::class)->refundOrder($order, 'trocar de lugar');

        $this->assertSame(0, $coupon->refresh()->used, 'o código volta a valer');
    }

    public function test_check_in_explains_a_transferred_ticket_in_portuguese(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok', 'document' => '52998224725']);
        $ticket = $order->tickets()->firstOrFail();
        $ticket->update(['status' => Ticket::STATUS_TRANSFERRED]);

        $result = app(CheckInService::class)->admit($ticket, $session, null);

        $this->assertSame(CheckIn::RESULT_DENIED, $result['result']);
        $this->assertSame('Ingresso transferido: vale o ingresso novo, com quem recebeu.', $result['reason']);
    }

    public function test_reloading_checkout_keeps_the_coupon_of_the_open_order(): void
    {
        $session = $this->makeSession(1, 4500);
        $this->coupon();
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => 'TESTE10', 'document' => '52998224725']);

        $this->actingAs($user)->get(route('checkout', $reservation))
            ->assertInertia(fn (Assert $p) => $p
                ->where('appliedCoupon', 'TESTE10')
                ->where('priceSummary.total', fn ($total) => (float) $total < 45));
    }
}
