<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Corridas do pós-pagamento: aprovação atrasada e emissão concorrente. */
class TicketIssuanceRaceTest extends TestCase
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

    public function test_late_approval_of_cancelled_order_refunds_instead_of_stealing_resold_seat(): void
    {
        $session = $this->makeSession(1, 4500);
        $seatId = $this->availableSeatIds($session, 1)[0];

        // Comprador A: hold + pedido + Pix pendente.
        $userA = User::factory()->create();
        $reservationA = app(SeatReservationService::class)->hold($session, $userA, [$seatId]);
        $orderA = app(OrderService::class)->createFromReservation($reservationA);
        $paymentA = Payment::create([
            'order_id' => $orderA->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => $orderA->total_cents,
            'gateway_payment_id' => 'MP-A',
        ]);

        // Pix vence: pedido cancelado, assento liberado.
        app(PaymentService::class)->expirePending($paymentA);
        $this->assertSame(Order::STATUS_CANCELLED, $orderA->refresh()->status);
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::find($seatId)->status);

        // Comprador B revende o assento (pago e emitido).
        $userB = User::factory()->create();
        $reservationB = app(SeatReservationService::class)->hold($session, $userB, [$seatId]);
        $orderB = app(OrderService::class)->createFromReservation($reservationB);
        app(TicketIssuanceService::class)->issueForOrder($orderB);
        $this->assertSame($orderB->id, SessionSeat::find($seatId)->sold_by_order_id);

        // Pix do A é pago no último segundo — webhook atrasado chega.
        app(PaymentService::class)->sync(
            $paymentA->refresh(),
            new PaymentResult('MP-A', PaymentResult::STATUS_APPROVED, 'pix', ['id' => 'MP-A']),
        );

        // O pedido cancelado NÃO pode reviver nem roubar o assento do B.
        $this->assertSame(Order::STATUS_CANCELLED, $orderA->refresh()->status);
        $this->assertSame(0, $orderA->tickets()->count(), 'pedido cancelado não pode emitir ingressos');
        $seat = SessionSeat::find($seatId);
        $this->assertSame(SessionSeat::STATUS_SOLD, $seat->status);
        $this->assertSame($orderB->id, $seat->sold_by_order_id, 'assento do comprador B foi sobrescrito');
        $this->assertSame(1, Ticket::where('order_id', $orderB->id)->where('status', Ticket::STATUS_VALID)->count());

        // O dinheiro do A volta: estorno no gateway + registro de reembolso.
        $this->assertContains('MP-A', $this->gateway->refunds, 'pagamento atrasado deve ser estornado no gateway');
        $this->assertSame(Payment::STATUS_REFUNDED, $paymentA->refresh()->status);
        $this->assertSame(1, Refund::where('order_id', $orderA->id)->count());
    }

    public function test_stale_model_cannot_issue_tickets_twice(): void
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);

        // Dois processos (webhook e reconcile) carregam o pedido ANTES da emissão.
        $fromWebhook = Order::findOrFail($order->id);
        $fromReconcile = Order::findOrFail($order->id);

        app(TicketIssuanceService::class)->issueForOrder($fromWebhook);
        app(TicketIssuanceService::class)->issueForOrder($fromReconcile); // modelo stale (status pending em memória)

        $this->assertSame(1, Ticket::where('order_id', $order->id)->count(), 'emissão concorrente duplicou ingressos');
    }

    public function test_card_approved_after_hold_expired_and_seat_resold_does_not_issue(): void
    {
        $session = $this->makeSession(1, 4500);
        $seatId = $this->availableSeatIds($session, 1)[0];

        // Comprador A: hold + pedido pendente (cartão em análise). Hold vence e é liberado.
        $userA = User::factory()->create();
        $reservationA = app(SeatReservationService::class)->hold($session, $userA, [$seatId]);
        $orderA = app(OrderService::class)->createFromReservation($reservationA);
        Payment::create([
            'order_id' => $orderA->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_CARD,
            'status' => Payment::STATUS_PENDING,
            'amount_cents' => $orderA->total_cents,
            'gateway_payment_id' => 'MP-CARD-A',
        ]);
        $this->travel(15)->minutes();
        app(SeatReservationService::class)->expireDueHolds();
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::find($seatId)->status);

        // Comprador B compra o assento.
        $userB = User::factory()->create();
        $reservationB = app(SeatReservationService::class)->hold($session, $userB, [$seatId]);
        $orderB = app(OrderService::class)->createFromReservation($reservationB);
        app(TicketIssuanceService::class)->issueForOrder($orderB);

        // Cartão do A aprova atrasado: não pode emitir nem sobrescrever o assento.
        app(TicketIssuanceService::class)->issueForOrder($orderA->refresh());

        $this->assertSame(0, $orderA->refresh()->tickets()->count());
        $this->assertNotSame(Order::STATUS_PAID, $orderA->status);
        $this->assertSame($orderB->id, SessionSeat::find($seatId)->sold_by_order_id);
    }
}
