<?php

namespace Tests\Feature\Kena;

use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class SessionCancellationTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_cancelling_session_refunds_all_paid_orders_and_frees_seats(): void
    {
        Mail::fake();
        $session = $this->makeSession(4);

        // Dois compradores, 1 assento cada.
        foreach (range(1, 2) as $i) {
            $buyer = User::factory()->create();
            $reservation = app(SeatReservationService::class)
                ->hold($session, $buyer, $this->availableSeatIds($session, 1));
            $order = app(OrderService::class)->createFromReservation($reservation);
            app(TicketIssuanceService::class)->issueForOrder($order);
        }
        $this->assertSame(2, SessionSeat::where('status', SessionSeat::STATUS_SOLD)->count());

        $organizer = PanelUser::factory()->create();
        $this->actingAs($organizer, 'painel')
            ->post(route('admin.sessions.cancel', $session))
            ->assertRedirect();

        $this->assertSame('cancelled', $session->refresh()->status);
        $this->assertSame(2, Order::where('status', Order::STATUS_REFUNDED)->count());
        $this->assertSame(0, SessionSeat::where('status', SessionSeat::STATUS_SOLD)->count());
        Mail::assertQueued(RefundConfirmedMail::class, 2);
    }
}
