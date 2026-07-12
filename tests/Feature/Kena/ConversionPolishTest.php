<?php

namespace Tests\Feature\Kena;

use App\Mail\EventReminderMail;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Sector;
use App\Models\User;
use App\Models\Venue;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class ConversionPolishTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_home_search_filters_events(): void
    {
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        foreach (['O Quebra-Nozes' => 'quebra-nozes', 'Show de Rock' => 'show-rock'] as $title => $slug) {
            $event = Event::create([
                'venue_id' => $venue->id, 'slug' => $slug, 'title' => $title,
                'kicker' => 'Teste', 'description' => 'd', 'status' => 'on_sale',
            ]);
            Sector::create(['event_id' => $event->id, 'name' => 'Plateia', 'price_cents' => 4500]);
            EventSession::create(['event_id' => $event->id, 'starts_at' => now()->addDays(10), 'status' => 'on_sale']);
        }

        $this->get('/eventos?q=quebra')
            ->assertInertia(fn (Assert $p) => $p->component('buyer/events')->where('q', 'quebra')->has('events.data', 1));
    }

    public function test_ticket_calendar_returns_ics(): void
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);
        $ticket = $order->tickets()->firstOrFail();

        $response = $this->actingAs($buyer)->get(route('tickets.calendar', $ticket));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=UTF-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('SUMMARY:', $response->getContent());
    }

    public function test_reminders_send_once_per_session(): void
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $session->update(['starts_at' => now()->addHours(20)]); // dentro da janela
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        $this->artisan('kena:send-reminders')->assertExitCode(0);
        $this->artisan('kena:send-reminders')->assertExitCode(0); // 2ª vez não reenvia

        $this->assertNotNull($session->refresh()->reminded_at);
        Mail::assertQueued(EventReminderMail::class, 1);
    }
}
