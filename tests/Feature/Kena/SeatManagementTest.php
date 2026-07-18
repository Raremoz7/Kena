<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
use App\Models\SessionSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class SeatManagementTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_organizer_blocks_and_unblocks_available_seat(): void
    {
        $session = $this->makeSession(2);
        $organizer = PanelUser::factory()->create();
        $seat = SessionSeat::where('session_id', $session->id)->firstOrFail();

        $this->actingAs($organizer, 'painel')
            ->postJson(route('admin.seats.toggle'), ['session_seat_id' => $seat->id])
            ->assertOk()
            ->assertJson(['status' => SessionSeat::STATUS_BLOCKED]);
        $this->assertSame(SessionSeat::STATUS_BLOCKED, $seat->refresh()->status);

        $this->actingAs($organizer, 'painel')
            ->postJson(route('admin.seats.toggle'), ['session_seat_id' => $seat->id])
            ->assertOk()
            ->assertJson(['status' => SessionSeat::STATUS_AVAILABLE]);
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, $seat->refresh()->status);
    }

    public function test_sold_seat_cannot_be_blocked(): void
    {
        $session = $this->makeSession(1);
        $organizer = PanelUser::factory()->create();
        $seat = SessionSeat::where('session_id', $session->id)->firstOrFail();
        $seat->update(['status' => SessionSeat::STATUS_SOLD]);

        $this->actingAs($organizer, 'painel')
            ->postJson(route('admin.seats.toggle'), ['session_seat_id' => $seat->id])
            ->assertStatus(422);
        $this->assertSame(SessionSeat::STATUS_SOLD, $seat->refresh()->status);
    }

    public function test_buyer_cannot_open_seats_admin(): void
    {
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('admin.seats', $session))->assertRedirect(route('painel.login'));
    }
}
