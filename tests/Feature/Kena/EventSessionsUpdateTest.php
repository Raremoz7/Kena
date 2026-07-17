<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Seat;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSessionsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_adds_and_removes_sessions(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A1', 'line' => 'A', 'number' => '1', 'pos_x' => 1, 'pos_y' => 1, 'kind' => 'standard']);

        $this->actingAs($organizer)->post('/painel/eventos', [
            'venue_id' => $venue->id,
            'title' => 'Editável',
            'kicker' => 'Show',
            'description' => 'desc',
            'status' => 'on_sale',
            'banner_from' => 'a',
            'banner_to' => 'b',
            'sector_name' => 'Plateia',
            'price' => 40,
            'sessions' => [['starts_at' => now()->addDays(5)->format('Y-m-d\TH:i')]],
        ]);

        $event = Event::where('title', 'Editável')->firstOrFail();
        $first = $event->sessions()->firstOrFail();
        $this->assertSame(1, $event->sessions()->count());

        // Atualiza mantendo a 1ª e adicionando uma 2ª.
        $this->actingAs($organizer)->put("/painel/eventos/{$event->id}", [
            'venue_id' => $venue->id,
            'title' => 'Editável',
            'kicker' => 'Show',
            'description' => 'desc',
            'status' => 'on_sale',
            'banner_from' => 'a',
            'banner_to' => 'b',
            'sector_name' => 'Plateia',
            'price' => 40,
            'sessions' => [
                ['id' => $first->id, 'starts_at' => now()->addDays(6)->format('Y-m-d\TH:i')],
                ['starts_at' => now()->addDays(7)->format('Y-m-d\TH:i')],
            ],
        ])->assertRedirect(route('admin.events'));

        $this->assertSame(2, $event->sessions()->count());
        $this->assertSame(1, EventSession::where('id', $first->id)->whereDate('starts_at', now()->addDays(6)->toDateString())->count());

        // Atualiza removendo a 2ª (sem vendidos → pode remover).
        $this->actingAs($organizer)->put("/painel/eventos/{$event->id}", [
            'venue_id' => $venue->id,
            'title' => 'Editável',
            'kicker' => 'Show',
            'description' => 'desc',
            'status' => 'on_sale',
            'banner_from' => 'a',
            'banner_to' => 'b',
            'sector_name' => 'Plateia',
            'price' => 40,
            'sessions' => [['id' => $first->id, 'starts_at' => now()->addDays(6)->format('Y-m-d\TH:i')]],
        ])->assertRedirect(route('admin.events'));

        $this->assertSame(1, $event->sessions()->count());
        $this->assertTrue(EventSession::where('id', $first->id)->exists());
    }
}
