<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class VenueSeatImportTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Venue
    {
        return Venue::create(['name' => 'Novo Teatro', 'city' => 'SP', 'state' => 'SP']);
    }

    private function organizer(): User
    {
        return User::factory()->create(['role' => User::ROLE_ORGANIZER]);
    }

    public function test_generate_grid_creates_seats(): void
    {
        $venue = $this->venue();

        $this->actingAs($this->organizer())
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 3, 'seats_per_row' => 4])
            ->assertRedirect();

        $this->assertSame(12, Seat::where('venue_id', $venue->id)->count());
        $this->assertDatabaseHas('seats', ['venue_id' => $venue->id, 'code' => 'A1', 'line' => 'A']);
        $this->assertDatabaseHas('seats', ['venue_id' => $venue->id, 'code' => 'C4', 'line' => 'C']);
    }

    public function test_import_json_creates_seats(): void
    {
        $venue = $this->venue();
        $json = json_encode([
            ['code' => 'A1', 'line' => 'A', 'number' => '1', 'x' => 10, 'y' => 10, 'kind' => 'standard'],
            ['code' => 'A2', 'line' => 'A', 'number' => '2', 'x' => 34, 'y' => 10],
        ]);
        $file = UploadedFile::fake()->createWithContent('map.json', (string) $json);

        $this->actingAs($this->organizer())
            ->post(route('admin.venues.seats.import', $venue), ['file' => $file])
            ->assertRedirect();

        $this->assertSame(2, Seat::where('venue_id', $venue->id)->count());
    }

    public function test_map_editable_when_venue_has_events_without_sales(): void
    {
        // Evento sem NENHUMA venda: o mapa continua reeditável (M12).
        $venue = $this->venue();
        Event::create([
            'venue_id' => $venue->id, 'slug' => 'ev', 'title' => 'Ev',
            'kicker' => 'x', 'description' => 'd', 'status' => 'on_sale',
        ]);

        $this->actingAs($this->organizer())
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 2, 'seats_per_row' => 2])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(4, Seat::where('venue_id', $venue->id)->count());
    }

    public function test_buyer_cannot_import(): void
    {
        $venue = $this->venue();
        $buyer = User::factory()->create(['role' => User::ROLE_BUYER]);

        $this->actingAs($buyer)
            ->post(route('admin.venues.seats.generate', $venue), ['rows' => 2, 'seats_per_row' => 2])
            ->assertForbidden();
    }
}
