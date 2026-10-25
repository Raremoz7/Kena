<?php

namespace Tests\Feature\Kena;

use App\Exceptions\SeatConflictException;
use App\Mail\TicketsIssuedMail;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\TicketLot;
use App\Models\User;
use App\Services\SeatReservationService;
use App\Services\TicketLotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Painel: bloquear/liberar vários lugares e emitir ingressos nominais da organização. */
class AdminSeatsTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    public function test_bulk_block_and_release_skip_sold_and_held_seats(): void
    {
        $session = $this->makeSession(4);
        $ids = $this->availableSeatIds($session, 4);
        app(SeatReservationService::class)->hold($session, User::factory()->create(), [$ids[3]]);

        $this->actingAs($this->organizer(), 'painel')
            ->postJson(route('admin.seats.bulk', $session), ['session_seat_ids' => $ids, 'action' => 'block'])
            ->assertOk()
            ->assertJsonPath('changed', 3);

        $this->assertSame(3, SessionSeat::where('status', SessionSeat::STATUS_BLOCKED)->count());
        $this->assertSame(SessionSeat::STATUS_HELD, SessionSeat::find($ids[3])->status);

        $this->actingAs($this->organizer(), 'painel')
            ->postJson(route('admin.seats.bulk', $session), ['session_seat_ids' => $ids, 'action' => 'release'])
            ->assertJsonPath('changed', 3);

        $this->assertSame(0, SessionSeat::where('status', SessionSeat::STATUS_BLOCKED)->count());
    }

    public function test_blocked_seats_cannot_be_bought(): void
    {
        $session = $this->makeSession(2);
        $ids = $this->availableSeatIds($session, 1);
        SessionSeat::whereIn('id', $ids)->update(['status' => SessionSeat::STATUS_BLOCKED]);

        $this->expectException(SeatConflictException::class);
        app(SeatReservationService::class)->hold($session, User::factory()->create(), $ids);
    }

    public function test_issue_named_free_tickets_for_blocked_seats(): void
    {
        Mail::fake();
        $session = $this->makeSession(3);
        $ids = $this->availableSeatIds($session, 3);
        SessionSeat::whereIn('id', $ids)->update(['status' => SessionSeat::STATUS_BLOCKED]);

        $this->actingAs($this->organizer(), 'painel')
            ->postJson(route('admin.seats.issue', $session), ['tickets' => [
                ['session_seat_id' => $ids[0], 'name' => 'Diretora Ana', 'email' => 'gestao@escola.test'],
                ['session_seat_id' => $ids[1], 'name' => 'Coordenadora Bia', 'email' => 'GESTAO@escola.test'],
                ['session_seat_id' => $ids[2], 'name' => 'Professora Carla', 'email' => 'carla@escola.test'],
            ]])
            ->assertOk();

        $this->assertSame(3, SessionSeat::whereIn('id', $ids)->where('status', SessionSeat::STATUS_SOLD)->count());
        // Um pedido grátis por e-mail; cada ingresso com o nome de quem vai usar.
        $this->assertSame(2, Order::where('status', Order::STATUS_PAID)->where('total_cents', 0)->count());
        $this->assertEqualsCanonicalizing(
            ['Diretora Ana', 'Coordenadora Bia', 'Professora Carla'],
            Ticket::pluck('holder_name')->all(),
        );
        $this->assertSame(2, Ticket::whereHas('user', fn ($q) => $q->where('email', 'gestao@escola.test'))->count());
        Mail::assertQueued(TicketsIssuedMail::class, 2);
    }

    public function test_issue_refuses_sold_or_held_seats(): void
    {
        Mail::fake();
        $session = $this->makeSession(2);
        $ids = $this->availableSeatIds($session, 2);
        app(SeatReservationService::class)->hold($session, User::factory()->create(), [$ids[0]]);

        $this->actingAs($this->organizer(), 'painel')
            ->postJson(route('admin.seats.issue', $session), ['tickets' => [
                ['session_seat_id' => $ids[0], 'name' => 'X', 'email' => 'x@escola.test'],
                ['session_seat_id' => $ids[1], 'name' => 'Y', 'email' => 'y@escola.test'],
            ]])
            ->assertStatus(422);

        // Tudo ou nada: o lugar livre também não foi emitido.
        $this->assertSame(0, Ticket::count());
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::find($ids[1])->status);
    }

    public function test_issued_tickets_do_not_count_in_the_lot(): void
    {
        Mail::fake();
        $session = $this->makeSession(3);
        $lot = TicketLot::create(['session_id' => $session->id, 'name' => '1º lote', 'price_cents' => 4500, 'quantity' => 2, 'position' => 0]);

        $this->actingAs($this->organizer(), 'painel')
            ->postJson(route('admin.seats.issue', $session), ['tickets' => [
                ['session_seat_id' => $this->availableSeatIds($session, 1)[0], 'name' => 'Gestão', 'email' => 'g@escola.test'],
            ]])
            ->assertOk();

        $this->assertSame(2, app(TicketLotService::class)->remaining($lot));
    }

    public function test_staff_cannot_issue_or_block(): void
    {
        $session = $this->makeSession(1);
        $staff = PanelUser::factory()->create(['role' => PanelUser::ROLE_STAFF]);

        $this->actingAs($staff, 'painel')
            ->postJson(route('admin.seats.bulk', $session), ['session_seat_ids' => $this->availableSeatIds($session, 1), 'action' => 'block'])
            ->assertForbidden();
    }
}
