<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
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

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    private function staff(): PanelUser
    {
        return PanelUser::factory()->staff()->create();
    }

    public function test_staff_can_reach_check_in_but_not_sensitive_pages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'painel')->get(route('admin.checkin'))->assertOk();
        $this->actingAs($staff, 'painel')->get(route('admin.events'))->assertForbidden();
        $this->actingAs($staff, 'painel')->get(route('admin.coupons'))->assertForbidden();
        $this->actingAs($staff, 'painel')->get(route('admin.settings'))->assertForbidden();
        $this->actingAs($staff, 'painel')->get(route('admin.team'))->assertForbidden();
    }

    public function test_organizer_reaches_team_page(): void
    {
        $this->actingAs($this->organizer(), 'painel')->get(route('admin.team'))->assertOk();
    }

    /** Comprador logado nao tem sessao de painel: vai para o login do painel. */
    public function test_buyer_session_does_not_reach_the_panel(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('painel'))->assertRedirect(route('painel.login'));
        $this->actingAs($buyer)->get(route('admin.checkin'))->assertRedirect(route('painel.login'));
    }

    /** Conta de painel nao e conta de comprador: nao alcanca a area do comprador. */
    public function test_panel_session_does_not_reach_buyer_area(): void
    {
        $this->actingAs($this->organizer(), 'painel')
            ->get(route('tickets.index'))
            ->assertRedirect(route('login'));
    }

    public function test_organizer_creates_a_new_staff_account(): void
    {
        $this->actingAs($this->organizer(), 'painel')->post(route('admin.team.store'), [
            'name' => 'Portaria 1',
            'email' => 'portaria1@example.com',
            'role' => PanelUser::ROLE_STAFF,
            'password' => 'senha-da-portaria',
        ])->assertRedirect();

        $this->assertDatabaseHas('panel_users', [
            'email' => 'portaria1@example.com',
            'role' => PanelUser::ROLE_STAFF,
        ]);
        // A conta de painel nao vaza para a tabela do comprador.
        $this->assertDatabaseMissing('users', ['email' => 'portaria1@example.com']);
    }

    /** O e-mail de um comprador nao bloqueia a criacao da conta de painel. */
    public function test_buyer_email_can_also_be_a_panel_account(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->actingAs($this->organizer(), 'painel')->post(route('admin.team.store'), [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'role' => PanelUser::ROLE_STAFF,
            'password' => 'senha-da-ana',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('panel_users', ['email' => 'ana@example.com']);
    }

    public function test_removing_a_member_deletes_the_panel_account(): void
    {
        $member = $this->staff();

        $this->actingAs($this->organizer(), 'painel')
            ->delete(route('admin.team.destroy', $member))
            ->assertRedirect();

        $this->assertDatabaseMissing('panel_users', ['id' => $member->id]);
    }

    public function test_organizer_cannot_remove_themselves(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer, 'painel')->delete(route('admin.team.destroy', $organizer))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('panel_users', ['id' => $organizer->id]);
    }

    /** Painel sem organizador nenhum ficaria ingerenciavel. */
    public function test_last_organizer_cannot_be_removed(): void
    {
        $organizer = $this->organizer();
        $outro = $this->organizer();

        // Com dois organizadores, remover um e permitido.
        $this->actingAs($organizer, 'painel')->delete(route('admin.team.destroy', $outro))
            ->assertSessionHasNoErrors();

        // Agora so resta um: o staff nao serve de substituto.
        $this->staff();
        $this->actingAs($organizer, 'painel')->delete(route('admin.team.destroy', $organizer))
            ->assertSessionHasErrors();
    }

    public function test_venue_map_can_be_edited_when_there_are_no_sales(): void
    {
        // Venue com evento/sessão mas SEM nenhuma venda.
        $session = $this->makeSession(2, 4500);
        $venue = $session->event->venue;

        $this->actingAs($this->organizer(), 'painel')
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

        $this->actingAs($this->organizer(), 'painel')
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 5, 'seats_per_row' => 5])
            ->assertSessionHasErrors('seats');
    }
}
