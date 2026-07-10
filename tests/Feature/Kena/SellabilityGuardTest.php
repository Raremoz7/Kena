<?php

namespace Tests\Feature\Kena;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Só sessão vendável aceita reserva: evento publicado, sessão não cancelada e futura. */
class SellabilityGuardTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_cancelled_session_rejects_reservation(): void
    {
        $session = $this->makeSession(1);
        $session->update(['status' => 'cancelled']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $this->availableSeatIds($session, 1)],
        );

        $response->assertStatus(410);
        $this->assertSame(0, Reservation::count());
    }

    public function test_draft_event_rejects_reservation(): void
    {
        $session = $this->makeSession(1);
        $session->event->update(['status' => 'draft']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $this->availableSeatIds($session, 1)],
        );

        $response->assertStatus(410);
        $this->assertSame(0, Reservation::count());
    }

    public function test_past_session_rejects_reservation(): void
    {
        $session = $this->makeSession(1);
        $session->update(['starts_at' => now()->subHour()]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $this->availableSeatIds($session, 1)],
        );

        $response->assertStatus(410);
        $this->assertSame(0, Reservation::count());
    }

    public function test_event_page_does_not_expose_cancelled_session_map(): void
    {
        $session = $this->makeSession(1);
        $session->update(['status' => 'cancelled']);

        $this->get(route('events.show', $session->event->slug))->assertNotFound();
    }

    public function test_draft_event_page_is_not_public(): void
    {
        $session = $this->makeSession(1);
        $session->event->update(['status' => 'draft']);

        $this->get(route('events.show', $session->event->slug))->assertNotFound();
    }

    public function test_seats_page_of_draft_event_redirects_away(): void
    {
        $session = $this->makeSession(1);
        $session->event->update(['status' => 'draft']);

        $this->get(route('sessions.seats', ['slug' => $session->event->slug, 'session' => $session->id]))
            ->assertRedirect();
    }
}
