<?php

namespace Tests\Feature\Kena;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\EventSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * O mapa de assentos é o prop mais pesado do catálogo (um item por assento da
 * sessão). Ele fica abaixo da dobra em `buyer/event`, então não deve viajar no
 * payload inicial — e em eventos multi-sessão não deve ser consultado sequer.
 */
class SeatMapDeferralTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_seat_map_is_absent_from_the_initial_payload(): void
    {
        $session = $this->makeSession(3);

        $this->get(route('events.show', $session->event->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('buyer/event')
                ->missing('seatMap')
                ->has('sectors')          // o que fica acima da dobra continua eager
                ->has('availabilityUrl'));
    }

    public function test_seat_map_arrives_on_a_partial_reload(): void
    {
        $session = $this->makeSession(3);

        // A versão do asset precisa bater, senão o middleware responde 409 (reload forçado).
        $version = (new HandleInertiaRequests)->version(Request::create('/'));

        // Partial reload devolve JSON puro (não a view), então assertamos o payload direto.
        $response = $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'buyer/event',
            'X-Inertia-Partial-Data' => 'seatMap',
        ])->get(route('events.show', $session->event->slug));

        $response->assertOk()
            ->assertJsonPath('component', 'buyer/event')
            ->assertJsonCount(3, 'props.seatMap.seats')
            ->assertJsonStructure(['props' => ['seatMap' => ['seats', 'bounds', 'sectorName']]]);

        // O partial só carrega o que foi pedido — o resto não é recalculado.
        $response->assertJsonMissingPath('props.sectors');
    }

    public function test_multi_session_event_never_loads_the_seat_map(): void
    {
        $session = $this->makeSession(3);
        EventSession::create([
            'event_id' => $session->event_id,
            'starts_at' => now()->addDays(40),
            'status' => 'on_sale',
        ]);

        $this->get(route('events.show', $session->event->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('buyer/event')
                ->where('seatMap', null)
                ->has('sessions', 2));
    }
}
