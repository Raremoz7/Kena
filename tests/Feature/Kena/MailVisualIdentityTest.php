<?php

namespace Tests\Feature\Kena;

use App\Mail\EventReminderMail;
use App\Mail\RefundConfirmedMail;
use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class MailVisualIdentityTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function paidOrder(): Order
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_tickets_issued_uses_kena_dark_theme(): void
    {
        $html = (new TicketsIssuedMail($this->paidOrder()))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('KENA', $html);
        $this->assertStringContainsString('Pagamento aprovado', $html);
    }

    public function test_ticket_transferred_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();
        $ticket = $order->tickets()->firstOrFail();

        $html = (new TicketTransferredMail($ticket, 'Ana Souza'))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('Ana Souza', $html);
    }

    public function test_event_reminder_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();

        $html = (new EventReminderMail($order->session, $order->user))->render();

        $this->assertStringContainsString('#120C08', $html);
    }

    public function test_refund_confirmed_uses_kena_dark_theme(): void
    {
        $order = $this->paidOrder();

        $html = (new RefundConfirmedMail($order))->render();

        $this->assertStringContainsString('#120C08', $html);
        $this->assertStringContainsString('R$', $html);
    }
}
