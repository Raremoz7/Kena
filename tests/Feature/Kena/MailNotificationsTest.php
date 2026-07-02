<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Services\TicketTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class MailNotificationsTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        $session = $this->makeSession(2);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 2));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_issuing_tickets_queues_confirmation_email(): void
    {
        Mail::fake();
        $order = $this->paidOrder();

        Mail::assertQueued(TicketsIssuedMail::class, fn (TicketsIssuedMail $m) => $m->order->is($order));
    }

    public function test_transfer_queues_email_to_recipient(): void
    {
        $order = $this->paidOrder();
        $ticket = $order->tickets()->firstOrFail();
        $recipient = User::factory()->create(['email' => 'dest@kena.test']);

        Mail::fake();
        app(TicketTransferService::class)->transfer($ticket, $ticket->user, 'dest@kena.test');

        Mail::assertQueued(
            TicketTransferredMail::class,
            fn (TicketTransferredMail $m) => $m->hasTo('dest@kena.test'),
        );
    }

    public function test_confirmation_email_renders_with_inline_qr(): void
    {
        $order = $this->paidOrder();

        $html = (new TicketsIssuedMail($order))->render();

        $this->assertStringContainsString('Pagamento aprovado', $html);
        $this->assertStringContainsString($order->tickets()->first()->code, $html);
        // QR PNG real embutido (no envio real vira anexo inline cid:; no preview, data-uri).
        $this->assertStringContainsString('image/png', $html);
    }
}
