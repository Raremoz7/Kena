<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\RefundService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Guardas do reembolso: duplo estorno, pós-check-in e assento pós-sessão. */
class RefundGuardTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
    }

    /** @return array{0: Order, 1: User, 2: EventSession} */
    private function paidOrder(): array
    {
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return [$order->refresh(), $user, $session];
    }

    public function test_concurrent_refund_requests_record_only_one_refund(): void
    {
        [$order] = $this->paidOrder();

        // Duas requisições carregam o pedido PAGO antes de qualquer estorno.
        $first = Order::findOrFail($order->id);
        $second = Order::findOrFail($order->id);

        app(RefundService::class)->refundOrder($first, 'primeira');

        try {
            app(RefundService::class)->refundOrder($second, 'segunda'); // stale PAID em memória
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame(1, Refund::count(), 'duplo clique não pode registrar dois reembolsos');
    }

    public function test_buyer_cannot_refund_after_check_in(): void
    {
        [$order, $user, $session] = $this->paidOrder();
        $ticket = Ticket::where('order_id', $order->id)->firstOrFail();
        app(CheckInService::class)->admit($ticket, $session, null);

        $response = $this->actingAs($user)->postJson(route('orders.refund', $order));

        $response->assertStatus(422);
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }

    public function test_refund_after_session_started_does_not_resell_the_seat(): void
    {
        [$order, , $session] = $this->paidOrder();
        $seatId = Ticket::where('order_id', $order->id)->firstOrFail()->session_seat_id;

        // Sessão já começou; organizador estorna mesmo assim.
        $session->update(['starts_at' => now()->subHour()]);
        app(RefundService::class)->refundOrder($order->refresh(), 'organizador');

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertNotSame(
            SessionSeat::STATUS_AVAILABLE,
            SessionSeat::find($seatId)->status,
            'assento de sessão em andamento não pode voltar pra venda',
        );
    }
}
