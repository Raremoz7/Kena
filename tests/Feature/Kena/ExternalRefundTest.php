<?php

namespace Tests\Feature\Kena;

use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\RefundService;
use App\Services\SeatReservationService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Estorno ou chargeback feito fora do Kena precisa derrubar o ingresso. */
class ExternalRefundTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        Mail::fake();
    }

    private function paidOrder(): Order
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);

        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-1',
        ]);
        $this->webhook('evt-approved');

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);

        return $order;
    }

    private function webhook(string $eventId): void
    {
        app(WebhookService::class)->handleMercadoPago(
            ['type' => 'payment', 'id' => $eventId, 'data' => ['id' => 'MP-1']],
        );
    }

    public function test_refund_made_in_mercado_pago_cancels_ticket_and_frees_seat(): void
    {
        $order = $this->paidOrder();

        $this->gateway->fetchStatus = PaymentResult::STATUS_REFUNDED;
        $this->gateway->fetchMpStatus = 'refunded';
        $this->webhook('evt-refunded');

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(Ticket::STATUS_REFUNDED, $order->tickets()->first()->status);
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::first()->status);
        $this->assertSame(1, Refund::where('status', Refund::STATUS_DONE)->count());
        $this->assertSame([], $this->gateway->refunds, 'o dinheiro já voltou: não estorna de novo');
        Mail::assertQueued(RefundConfirmedMail::class);
    }

    public function test_chargeback_cancels_ticket(): void
    {
        $order = $this->paidOrder();

        $this->gateway->fetchStatus = PaymentResult::STATUS_REFUNDED;
        $this->gateway->fetchMpStatus = 'charged_back';
        $this->webhook('evt-chargeback');

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(Ticket::STATUS_REFUNDED, $order->tickets()->first()->status);
        $this->assertStringContainsString('chargeback', (string) Refund::first()?->reason);
    }

    public function test_repeated_refund_notification_records_a_single_refund(): void
    {
        $this->paidOrder();

        $this->gateway->fetchStatus = PaymentResult::STATUS_REFUNDED;
        $this->webhook('evt-refunded-1');
        $this->webhook('evt-refunded-2');

        $this->assertSame(1, Refund::count());
    }

    public function test_refund_from_kena_panel_is_not_duplicated_by_its_webhook(): void
    {
        $order = $this->paidOrder();

        app(RefundService::class)->refundOrder($order, 'Pedido do cliente');

        $this->gateway->fetchStatus = PaymentResult::STATUS_REFUNDED;
        $this->webhook('evt-refunded');

        $this->assertSame(1, Refund::count());
        $this->assertSame('Pedido do cliente', Refund::first()?->reason);
        $this->assertSame(['MP-1'], $this->gateway->refunds);
    }
}
