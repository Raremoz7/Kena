<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Um pedido não pode acumular dois meios de pagamento vivos (Pix + cartão). */
class DoublePaymentGuardTest extends TestCase
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

    /** @return Reservation */
    private function makeReservation()
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();

        return app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
    }

    public function test_paying_with_card_cancels_pending_pix_at_gateway(): void
    {
        $reservation = $this->makeReservation();

        // 1ª tentativa: Pix (fica pendente, QR vivo por ~30min).
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'pix']);
        $pix = Payment::where('order_id', $order->id)->where('method', Payment::METHOD_PIX)->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $pix->status);

        // 2ª tentativa: usuário desiste do Pix e paga com cartão.
        $order = app(PaymentService::class)->pay($reservation->refresh(), ['method' => 'card', 'token' => 'tok_x']);

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertContains($pix->gateway_payment_id, $this->gateway->cancellations, 'o Pix pendente deve ser cancelado no gateway');
        $this->assertSame(Payment::STATUS_CANCELLED, $pix->refresh()->status);
    }

    public function test_pix_payment_response_exposes_extended_reservation_deadline(): void
    {
        $reservation = $this->makeReservation();
        $originalExpiry = $reservation->expires_at;

        $response = $this->actingAs($reservation->user)->postJson(
            route('checkout.pay', $reservation),
            ['method' => 'pix'],
        );

        $response->assertOk();
        $deadline = $response->json('reservationExpiresAt');
        $this->assertNotNull($deadline, 'payload deve expor o prazo real da reserva');
        $this->assertTrue(
            Carbon::parse($deadline)->greaterThan($originalExpiry),
            'o prazo exposto deve refletir a extensão do hold pelo Pix',
        );
    }

    public function test_requesting_pix_twice_reuses_the_pending_payment(): void
    {
        $reservation = $this->makeReservation();

        app(PaymentService::class)->pay($reservation, ['method' => 'pix']);
        app(PaymentService::class)->pay($reservation->refresh(), ['method' => 'pix']); // clique repetido

        $this->assertSame(1, $this->gateway->pixCreated, 'não pode criar um segundo Pix no gateway');
        $this->assertSame(1, Payment::count());
    }
}
