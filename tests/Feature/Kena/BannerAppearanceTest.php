<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\PanelUser;
use App\Models\Seat;
use App\Models\Venue;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Degradê horizontal do topo do evento: cor e opacidade de cada ponta; a lista não tem degradê. */
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
            'banner_from_opacity' => 90,
            'banner_to_opacity' => 20,
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
        $this->assertSame(90, (int) $event->banner_from_opacity);
        $this->assertSame(20, (int) $event->banner_to_opacity);

        $this->actingAs($organizer, 'painel')
            ->put("/painel/eventos/{$event->id}", $this->payload($venue, ['banner_from' => '#112233', 'banner_to_opacity' => 60]))
            ->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('#112233', $event->banner_from);
        $this->assertSame(60, (int) $event->banner_to_opacity);
    }

    public function test_invalid_color_and_opacity_are_rejected(): void
    {
        $organizer = PanelUser::factory()->create();
        $venue = $this->venue();

        // Cor vai direto para o style do banner: só hex é aceito.
        $this->actingAs($organizer, 'painel')
            ->post('/painel/eventos', $this->payload($venue, ['banner_from' => 'red;background:url(x)', 'banner_from_opacity' => 150]))
            ->assertSessionHasErrors(['banner_from', 'banner_from_opacity']);

        $this->assertSame(0, Event::count());
    }

    public function test_defaults_keep_the_classic_dark_shade_and_only_the_event_page_gets_it(): void
    {
        $organizer = PanelUser::factory()->create();
        $venue = $this->venue();

        $this->actingAs($organizer, 'painel')
            ->post('/painel/eventos', $this->payload($venue, ['banner_from_opacity' => null, 'banner_to_opacity' => null]))
            ->assertSessionHasNoErrors();

        $event = Event::with(['venue', 'sectors', 'sessions'])->firstOrFail();
        // Padrão = a faixa escura de antes (94% → 35%).
        $this->assertSame(94, (int) $event->banner_from_opacity);
        $this->assertSame(35, (int) $event->banner_to_opacity);

        $detail = CatalogPresenter::detail($event);
        $this->assertSame(94, $detail['bannerFromOpacity']);
        $this->assertSame(35, $detail['bannerToOpacity']);

        // O cartão da lista não leva degradê.
        $card = CatalogPresenter::listItem($event);
        $this->assertArrayNotHasKey('bannerFrom', $card);
        $this->assertArrayNotHasKey('bannerFromOpacity', $card);
    }
}
