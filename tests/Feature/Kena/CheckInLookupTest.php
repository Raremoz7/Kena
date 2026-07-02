<?php

namespace Tests\Feature\Kena;

use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class CheckInLookupTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function issuedTicket(): Ticket
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->tickets()->firstOrFail();
    }

    public function test_lookup_then_admit_marks_used_and_blocks_reentry(): void
    {
        $ticket = $this->issuedTicket();
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $lookup = $this->actingAs($organizer)->postJson(route('admin.checkin.lookup'), [
            'session_id' => $ticket->session_id,
            'q' => substr($ticket->code, 0, 8),
        ]);
        $lookup->assertOk();
        $this->assertCount(1, $lookup->json('results'));
        $this->assertSame($ticket->id, $lookup->json('results.0.id'));

        $this->actingAs($organizer)->postJson(route('admin.checkin.admit'), [
            'session_id' => $ticket->session_id,
            'ticket_id' => $ticket->id,
        ])->assertOk()->assertJson(['result' => 'ok']);
        $this->assertSame(Ticket::STATUS_USED, $ticket->refresh()->status);

        // Segunda admissão → negada.
        $this->actingAs($organizer)->postJson(route('admin.checkin.admit'), [
            'session_id' => $ticket->session_id,
            'ticket_id' => $ticket->id,
        ])->assertOk()->assertJson(['result' => 'denied']);
    }

    public function test_buyer_cannot_lookup(): void
    {
        $ticket = $this->issuedTicket();
        $buyer = User::factory()->create(['role' => User::ROLE_BUYER]);

        $this->actingAs($buyer)->postJson(route('admin.checkin.lookup'), [
            'session_id' => $ticket->session_id,
            'q' => 'abc',
        ])->assertForbidden();
    }
}
