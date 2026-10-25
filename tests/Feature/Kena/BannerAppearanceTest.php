<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\PanelUser;
use App\Models\Seat;
use App\Models\Venue;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cores do degradê (hex do seletor) e opacidade do degradê sobre a foto do banner. */
class BannerAppearanceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(Venue $venue, array $override = []): array
    {
        return array_merge([
            'venue_id' => $venue->id,
            'title' => 'Quebra-Nozes',
            'kicker' => 'Ballet',
            'description' => 'Espetáculo.',
            'status' => 'on_sale',
            'banner_from' => '#7a1f3d',
            'banner_to' => '#0d0806',
            'banner_opacity' => 40,
            'sector_name' => 'Plateia',
            'price' => 45,
            'sessions' => [['starts_at' => now()->addDays(10)->format('Y-m-d\TH:i')]],
        ], $override);
    }

    private function venue(): Venue
    {
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        Seat::create(['venue_id' => $venue->id, 'code' => 'A1', 'line' => 'A', 'number' => '1', 'pos_x' => 1, 'pos_y' => 1, 'kind' => 'standard']);

        return $venue;
    }

    public function test_organizer_saves_custom_colors_and_opacity(): void
    {
        $organizer = PanelUser::factory()->create();
        $venue = $this->venue();

        $this->actingAs($organizer, 'painel')->post('/painel/eventos', $this->payload($venue))->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertSame('#7a1f3d', $event->banner_from);
        $this->assertSame(40, (int) $event->banner_opacity);

        $this->actingAs($organizer, 'painel')
            ->put("/painel/eventos/{$event->id}", $this->payload($venue, ['banner_from' => '#112233', 'banner_opacity' => 85]))
            ->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('#112233', $event->banner_from);
        $this->assertSame(85, (int) $event->banner_opacity);
    }

    public function test_invalid_color_and_opacity_are_rejected(): void
    {
        $organizer = PanelUser::factory()->create();
        $venue = $this->venue();

        // Cor vai direto para o style do banner: só hex é aceito.
        $this->actingAs($organizer, 'painel')
            ->post('/painel/eventos', $this->payload($venue, ['banner_from' => 'red;background:url(x)', 'banner_opacity' => 150]))
            ->assertSessionHasErrors(['banner_from', 'banner_opacity']);

        $this->assertSame(0, Event::count());
    }

    public function test_opacity_defaults_to_zero_and_reaches_the_site(): void
    {
        $organizer = PanelUser::factory()->create();
        $venue = $this->venue();

        $this->actingAs($organizer, 'painel')
            ->post('/painel/eventos', $this->payload($venue, ['banner_opacity' => null]))
            ->assertSessionHasNoErrors();

        $event = Event::with(['venue', 'sectors', 'sessions'])->firstOrFail();
        $this->assertSame(0, (int) $event->banner_opacity);

        $event->update(['banner_opacity' => 60]);
        $event->refresh()->load(['venue', 'sectors', 'sessions']);
        $this->assertSame(60, CatalogPresenter::detail($event)['bannerOpacity']);
        $this->assertSame(60, CatalogPresenter::listItem($event)['bannerOpacity']);
    }
}
