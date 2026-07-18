<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketTransferredMail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Services\TicketTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class TicketTransferGuestTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_transfer_to_new_email_creates_lightweight_account(): void
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);
        $ticket = $order->tickets()->firstOrFail();

        Mail::fake();
        $newTicket = app(TicketTransferService::class)
            ->transfer($ticket, $buyer, 'novo.titular@guest.test');

        $this->assertSame(Ticket::STATUS_TRANSFERRED, $ticket->refresh()->status);

        $recipient = User::where('email', 'novo.titular@guest.test')->firstOrFail();
        $this->assertNull($recipient->password);
        $this->assertSame($recipient->id, $newTicket->user_id);
        $this->assertSame('Novo Titular', $newTicket->holder_name);

        Mail::assertQueued(
            TicketTransferredMail::class,
            fn (TicketTransferredMail $m) => $m->hasTo('novo.titular@guest.test'),
        );
    }
}
