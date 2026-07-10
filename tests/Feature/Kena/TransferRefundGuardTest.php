<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Services\TicketTransferService;
use App\Support\Presenters\TicketPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Reembolso não pode derrubar ingresso que já foi transferido a outra pessoa. */
class TransferRefundGuardTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
    }

    /** @return array{0: Order, 1: User, 2: Ticket} pedido pago, comprador e ticket do destinatário */
    private function paidOrderWithTransferredTicket(): array
    {
        $session = $this->makeSession(1, 4500);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        $ticket = Ticket::where('order_id', $order->id)->firstOrFail();
        $recipientTicket = app(TicketTransferService::class)->transfer($ticket, $buyer, 'amigo@example.com');

        return [$order->refresh(), $buyer, $recipientTicket];
    }

    public function test_buyer_cannot_refund_order_with_transferred_ticket(): void
    {
        [$order, $buyer, $recipientTicket] = $this->paidOrderWithTransferredTicket();

        $response = $this->actingAs($buyer)
            ->postJson(route('orders.refund', $order));

        $response->assertStatus(422);
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status, 'pedido não pode ser reembolsado');
        $this->assertSame(Ticket::STATUS_VALID, $recipientTicket->refresh()->status, 'ingresso do destinatário foi derrubado');
    }

    public function test_recipient_ticket_does_not_offer_refund_of_someone_elses_order(): void
    {
        [, , $recipientTicket] = $this->paidOrderWithTransferredTicket();

        $item = TicketPresenter::item($recipientTicket->fresh(['session.event.venue', 'order']));

        $this->assertFalse($item['canRefund'], 'destinatário não pode reembolsar pedido de terceiro');
    }
}
