<?php

namespace Tests\Feature\Kena;

use App\Mail\OrderCancelledMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Services\SessionCancellationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Cancelamento de sessão: holds órfãos, avisos a pendentes e relatório de falhas. */
class SessionCancellationGuardTest extends TestCase
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

    public function test_cancelling_session_releases_holds_without_orders(): void
    {
        $session = $this->makeSession(2, 4500);
        $user = User::factory()->create();

        // Hold ativo SEM pedido (usuário escolhendo pagamento).
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        app(SessionCancellationService::class)->cancel($session);

        $this->assertSame(0, SessionSeat::where('status', SessionSeat::STATUS_HELD)->count(), 'holds órfãos devem ser liberados');
        $this->assertNotSame(Reservation::STATUS_ACTIVE ?? 'active', $reservation->refresh()->status);
    }

    public function test_cancelling_session_emails_pending_orders_and_kills_their_pix(): void
    {
        Mail::fake();
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));

        // Pedido pendente com Pix vivo.
        app(PaymentService::class)->pay($reservation, ['method' => 'pix']);
        $pix = Payment::firstOrFail();

        app(SessionCancellationService::class)->cancel($session->refresh());

        Mail::assertQueued(OrderCancelledMail::class);
        $this->assertContains($pix->gateway_payment_id, $this->gateway->cancellations, 'Pix de sessão cancelada deve morrer no gateway');
    }

    public function test_cancel_report_counts_refund_failures(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_APPROVED, 'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-PAGO',
        ]);
        app(TicketIssuanceService::class)->issueForOrder($order);

        // Gateway recusa o estorno.
        $this->gateway->refundOk = false;
        $report = app(SessionCancellationService::class)->cancel($session->refresh());

        $this->assertSame(1, $report['failed'], 'estorno recusado deve aparecer no relatório');
        $this->assertSame(0, $report['refunded']);
    }

    public function test_expired_pix_sends_cancellation_email(): void
    {
        Mail::fake();
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING, 'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-EXP',
        ]);

        app(PaymentService::class)->expirePending($payment);

        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
        Mail::assertQueued(OrderCancelledMail::class);
    }
}
