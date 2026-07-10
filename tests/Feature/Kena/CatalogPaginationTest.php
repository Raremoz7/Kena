<?php

namespace Tests\Feature\Kena;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Sector;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * O catálogo público usa Inertia::scroll() (paginator + merge em `events.data`).
 * O <InfiniteScroll> concatena via partial reload; a busca é visita completa e
 * portanto substitui a lista em vez de concatenar.
 */
class CatalogPaginationTest extends TestCase
{
    use RefreshDatabase;

    private const PER_PAGE = 12;

    private function makeEvents(int $count, string $titlePrefix = 'Evento'): Venue
    {
        $venue = Venue::create(['name' => 'Teatro Teste '.Str::random(5), 'city' => 'Brasília', 'state' => 'DF']);

        for ($i = 1; $i <= $count; $i++) {
            $event = Event::create([
                'venue_id' => $venue->id,
                'slug' => Str::slug($titlePrefix).'-'.$i,
                'title' => "{$titlePrefix} {$i}",
                'kicker' => 'Teste',
                'description' => 'd',
                'status' => 'on_sale',
            ]);
            Sector::create(['event_id' => $event->id, 'name' => 'Plateia', 'price_cents' => 4500]);
            EventSession::create(['event_id' => $event->id, 'starts_at' => now()->addDays(10), 'status' => 'on_sale']);
        }

        return $venue;
    }

    public function test_first_page_is_capped_and_advertises_the_next_one(): void
    {
        $this->makeEvents(15);

        $this->get('/eventos')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('buyer/events')
                ->has('events.data', self::PER_PAGE)
                ->where('events.current_page', 1)
                ->where('events.total', 15));
    }

    public function test_second_page_returns_the_remainder(): void
    {
        $this->makeEvents(15);

        $this->get('/eventos?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->has('events.data', 3)
                ->where('events.current_page', 2));
    }

    public function test_scroll_metadata_is_exposed_for_the_infinite_scroll_component(): void
    {
        $this->makeEvents(15);

        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ])->get('/eventos');

        $response->assertOk()
            // `mergeProps` diz ao cliente para concatenar em events.data, não substituir.
            ->assertJsonPath('mergeProps', ['events.data'])
            ->assertJsonPath('scrollProps.events.pageName', 'page')
            ->assertJsonPath('scrollProps.events.currentPage', 1)
            ->assertJsonPath('scrollProps.events.nextPage', 2)
            ->assertJsonPath('scrollProps.events.previousPage', null);
    }

    public function test_last_page_advertises_no_next_page(): void
    {
        $this->makeEvents(15);

        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ])->get('/eventos?page=2')
            ->assertOk()
            ->assertJsonPath('scrollProps.events.nextPage', null)
            ->assertJsonPath('scrollProps.events.previousPage', 1);
    }

    public function test_search_filters_and_resets_to_the_first_page(): void
    {
        $this->makeEvents(15);
        $this->makeEvents(1, 'Concerto');

        $this->get('/eventos?q=Concerto')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('q', 'Concerto')
                ->has('events.data', 1)
                ->where('events.current_page', 1));
    }
}
