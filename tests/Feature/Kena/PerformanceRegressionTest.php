<?php

namespace Tests\Feature\Kena;

use App\Models\SessionSeat;
use App\Models\TicketLot;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Services\TicketLotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Trava as otimizações da auditoria: polling enxuto, lotes em 3 queries, share() e ingressos sem N+1. */
class PerformanceRegressionTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
    }

    private function countQueries(callable $fn): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_poll_returns_only_occupied_seats(): void
    {
        $session = $this->makeSession(5);
        $ids = $this->availableSeatIds($session, 5);
        app(SeatReservationService::class)->hold($session, User::factory()->create(), [$ids[0]]);
        SessionSeat::whereKey($ids[1])->update(['status' => SessionSeat::STATUS_BLOCKED]);

        $seats = $this->getJson(route('api.sessions.availability', $session))->assertOk()->json('seats');

        $this->assertEqualsCanonicalizing([(string) $ids[0], (string) $ids[1]], array_map('strval', array_keys($seats)));
        $this->assertSame('held', $seats[$ids[0]]);
        $this->assertSame('blocked', $seats[$ids[1]]);
    }

    public function test_poll_sends_an_object_even_when_everything_is_free(): void
    {
        $session = $this->makeSession(2);

        $body = $this->getJson(route('api.sessions.availability', $session))->assertOk()->getContent();

        // Objeto vazio, não lista: o front indexa por id do assento.
        $this->assertSame('{"seats":{}}', $body);
    }

    public function test_poll_is_cached_briefly(): void
    {
        $session = $this->makeSession(3);
        $service = app(AvailabilityService::class);
        $service->unavailable($session);

        // Dentro da janela: muitos pais no mapa = nenhuma consulta nova aos assentos.
        $this->assertSame(0, $this->countQueries(fn () => $service->unavailable($session)));

        $this->travel(AvailabilityService::CACHE_SECONDS + 1)->seconds();
        $ids = $this->availableSeatIds($session, 1);
        SessionSeat::whereKey($ids[0])->update(['status' => SessionSeat::STATUS_BLOCKED]);

        $this->assertSame('blocked', $service->unavailable($session)[$ids[0]] ?? null);
    }

    public function test_lot_snapshot_costs_three_queries_regardless_of_lots(): void
    {
        $session = $this->makeSession(6);
        foreach (range(0, 3) as $i) {
            TicketLot::create(['session_id' => $session->id, 'name' => "Lote $i", 'price_cents' => 4500 + $i, 'quantity' => 2, 'position' => $i]);
        }

        $queries = $this->countQueries(function () use ($session): void {
            $snapshot = app(TicketLotService::class)->snapshot($session);
            $snapshot->current();
            $snapshot->summary();
            $snapshot->currentPriceCents();
        });

        $this->assertSame(3, $queries);
    }

    public function test_seat_page_does_not_multiply_lot_queries(): void
    {
        $session = $this->makeSession(4);
        $session->load('event');
        TicketLot::create(['session_id' => $session->id, 'name' => '1º', 'price_cents' => 4500, 'quantity' => 2, 'position' => 0]);
        TicketLot::create(['session_id' => $session->id, 'name' => '2º', 'price_cents' => 6000, 'quantity' => null, 'position' => 1]);
        $url = route('sessions.seats', ['slug' => $session->event->slug, 'session' => $session->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $lotQueries = collect(DB::getQueryLog())
            ->filter(fn (array $q): bool => str_contains($q['query'], 'ticket_lots') || str_contains($q['query'], 'lot_id'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(3, $lotQueries, 'a página de assentos deve montar um único retrato dos lotes');
    }

    public function test_shared_auth_sends_only_the_fields_the_ui_uses(): void
    {
        $user = User::factory()->create(['google_id' => 'g-123', 'phone' => '61999990000']);
        $session = $this->makeSession(1);
        $session->load('event');

        $this->actingAs($user)
            ->get(route('events.show', $session->event->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user', fn ($shared): bool => array_keys(collect($shared)->all()) === ['id', 'name', 'email', 'email_verified_at', 'hasCpf']));
    }

    public function test_tickets_page_queries_do_not_grow_with_tickets(): void
    {
        $session = $this->makeSession(8);
        $user = User::factory()->create();
        $buy = function (int $seats) use ($session, $user): void {
            $r = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, $seats));
            app(PaymentService::class)->pay($r, ['method' => 'card', 'token' => 't']);
        };

        $buy(1);
        $one = $this->countQueries(fn () => $this->actingAs($user)->get(route('tickets.index'))->assertOk());

        $buy(3);
        $buy(2);
        $six = $this->countQueries(fn () => $this->actingAs($user)->get(route('tickets.index'))->assertOk());

        $this->assertSame($one, $six);
    }
}
