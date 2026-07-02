<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class MailPreviewTest extends TestCase
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

    public function test_previews_each_known_mail_type(): void
    {
        $this->paidOrder();

        foreach (['tickets-issued', 'ticket-transferred', 'event-reminder', 'refund-confirmed'] as $type) {
            $this->get("/dev/mail-preview/{$type}")->assertOk();
        }
    }

    public function test_unknown_type_is_404(): void
    {
        $this->paidOrder();

        $this->get('/dev/mail-preview/nao-existe')->assertNotFound();
    }

    public function test_404_without_seeded_data(): void
    {
        $this->get('/dev/mail-preview/tickets-issued')->assertNotFound();
    }
}
