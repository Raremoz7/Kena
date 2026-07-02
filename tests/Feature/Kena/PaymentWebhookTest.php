<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SessionSeat;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_webhook_confirms_payment_and_is_idempotent(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);

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
            'gateway_payment_id' => 'MP-1',
        ]);

        $payload = ['type' => 'payment', 'id' => 'evt-1', 'data' => ['id' => 'MP-1']];

        app(WebhookService::class)->handleMercadoPago($payload);
        app(WebhookService::class)->handleMercadoPago($payload); // repetido

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(1, $order->tickets()->count(), 'não pode reemitir ingressos');
        $this->assertSame(Payment::STATUS_APPROVED, $payment->refresh()->status);
        $this->assertSame(SessionSeat::STATUS_SOLD, SessionSeat::first()->status);
        $this->assertSame(1, WebhookEvent::count());
    }
}
