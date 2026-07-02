<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_create_event_with_banner_image(): void
    {
        Storage::fake('public');

        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A1', 'line' => 'A', 'number' => '1', 'pos_x' => 1, 'pos_y' => 1, 'kind' => 'standard']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A2', 'line' => 'A', 'number' => '2', 'pos_x' => 2, 'pos_y' => 1, 'kind' => 'standard']);

        $response = $this->actingAs($organizer)->post('/dashboard/eventos', [
            'venue_id' => $venue->id,
            'title' => 'Evento com Banner',
            'kicker' => 'Show',
            'description' => 'Descrição do evento.',
            'status' => 'on_sale',
            'banner_from' => 'oklch(0.3 0.08 22)',
            'banner_to' => 'oklch(0.14 0.012 48)',
            'sector_name' => 'Plateia',
            'price' => 80,
            'sessions' => [['starts_at' => now()->addDays(10)->format('Y-m-d\TH:i')]],
            'banner_image' => UploadedFile::fake()->image('banner.jpg', 1200, 480),
        ]);

        $response->assertRedirect(route('admin.events'));

        $event = Event::where('title', 'Evento com Banner')->firstOrFail();
        $this->assertNotNull($event->banner_image);
        $this->assertStringContainsString('banners/', $event->banner_image);
        $this->assertSame(2, $event->sessions()->first()->sessionSeats()->count());
    }

    public function test_event_can_have_multiple_sessions_each_with_its_own_map(): void
    {
        Storage::fake('public');

        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A1', 'line' => 'A', 'number' => '1', 'pos_x' => 1, 'pos_y' => 1, 'kind' => 'standard']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A2', 'line' => 'A', 'number' => '2', 'pos_x' => 2, 'pos_y' => 1, 'kind' => 'standard']);

        $this->actingAs($organizer)->post('/dashboard/eventos', [
            'venue_id' => $venue->id,
            'title' => 'Temporada',
            'kicker' => 'Show',
            'description' => 'Várias sessões.',
            'status' => 'on_sale',
            'banner_from' => 'a',
            'banner_to' => 'b',
            'sector_name' => 'Plateia',
            'price' => 50,
            'sessions' => [
                ['starts_at' => now()->addDays(10)->format('Y-m-d\TH:i')],
                ['starts_at' => now()->addDays(11)->format('Y-m-d\TH:i')],
                ['starts_at' => now()->addDays(12)->format('Y-m-d\TH:i')],
            ],
        ])->assertRedirect(route('admin.events'));

        $event = Event::where('title', 'Temporada')->firstOrFail();
        $this->assertSame(3, $event->sessions()->count());
        // cada sessão gera seu próprio mapa (2 assentos)
        foreach ($event->sessions as $session) {
            $this->assertSame(2, $session->sessionSeats()->count());
        }
    }
}
