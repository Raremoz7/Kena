<?php

namespace Tests\Feature\Kena;

use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Autorização granular (staff × organizer) e gestão de equipe. */
class AdminAccessAndTeamTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function organizer(): User
    {
        return User::factory()->create(['role' => User::ROLE_ORGANIZER]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => User::ROLE_STAFF]);
    }

    public function test_staff_can_reach_check_in_but_not_sensitive_pages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('admin.checkin'))->assertOk();
        $this->actingAs($staff)->get(route('admin.events'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.coupons'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.team'))->assertForbidden();
    }

    public function test_organizer_reaches_team_page(): void
    {
        $this->actingAs($this->organizer())->get(route('admin.team'))->assertOk();
    }

    public function test_organizer_invites_a_new_staff_member(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post(route('admin.team.store'), [
            'name' => 'Portaria 1',
            'email' => 'portaria1@example.com',
            'role' => User::ROLE_STAFF,
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'portaria1@example.com',
            'role' => User::ROLE_STAFF,
        ]);
    }

    public function test_organizer_promotes_existing_buyer_to_staff(): void
    {
        $organizer = $this->organizer();
        $buyer = User::factory()->create(['role' => User::ROLE_BUYER, 'email' => 'ana@example.com']);

        $this->actingAs($organizer)->post(route('admin.team.store'), [
            'name' => 'Ana', 'email' => 'ana@example.com', 'role' => User::ROLE_STAFF,
        ])->assertRedirect();

        $this->assertSame(User::ROLE_STAFF, $buyer->refresh()->role);
    }

    public function test_removing_a_member_demotes_to_buyer(): void
    {
        $organizer = $this->organizer();
        $member = $this->staff();

        $this->actingAs($organizer)->delete(route('admin.team.destroy', $member))->assertRedirect();

        $this->assertSame(User::ROLE_BUYER, $member->refresh()->role);
    }

    public function test_organizer_cannot_remove_themselves(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->delete(route('admin.team.destroy', $organizer))
            ->assertSessionHasErrors();

        $this->assertSame(User::ROLE_ORGANIZER, $organizer->refresh()->role);
    }

    public function test_venue_map_can_be_edited_when_there_are_no_sales(): void
    {
        // Venue com evento/sessão mas SEM nenhuma venda.
        $session = $this->makeSession(2, 4500);
        $venue = $session->event->venue;

        $this->actingAs($this->organizer())
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 2, 'seats_per_row' => 3])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Mapa regenerado (2×3 = 6) e session_seats re-sincronizados.
        $this->assertSame(6, $venue->seats()->count());
        $this->assertSame(6, SessionSeat::where('session_id', $session->id)->count());
    }

    public function test_venue_map_stays_locked_when_there_are_sold_seats(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $session = $this->makeSession(2, 4500);
        $venue = $session->event->venue;

        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        $this->actingAs($this->organizer())
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 5, 'seats_per_row' => 5])
            ->assertSessionHasErrors('seats');
    }
}
