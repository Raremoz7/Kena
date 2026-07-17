<?php

namespace Tests\Feature\Kena;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * As listas do painel são paginadas (25/página) e expõem o envelope do paginator
 * (`data` + `links`), consumido pelo componente <Pagination>. O overview é a
 * exceção: mostra um resumo limitado e linka "Ver todos".
 */
class AdminListsPaginationTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private const PER_PAGE = 25;

    private function organizer(): User
    {
        return User::factory()->create(['role' => User::ROLE_ORGANIZER]);
    }

    public function test_coupons_are_paginated(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Coupon::create([
                'code' => 'CUPOM'.$i,
                'type' => Coupon::TYPE_PERCENT,
                'value' => 10,
                'active' => true,
            ]);
        }

        $this->actingAs($this->organizer())
            ->get(route('admin.coupons'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/coupons')
                ->has('coupons.data', self::PER_PAGE)
                ->has('coupons.links'));

        $this->actingAs($this->organizer())
            ->get(route('admin.coupons', ['page' => 2]))
            ->assertInertia(fn (Assert $p) => $p->has('coupons.data', 5));
    }

    public function test_venues_are_paginated(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Venue::create(['name' => 'Local '.Str::padLeft((string) $i, 2, '0'), 'city' => 'Brasília', 'state' => 'DF']);
        }

        $this->actingAs($this->organizer())
            ->get(route('admin.venues'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/venues')
                ->has('venues.data', self::PER_PAGE)
                ->has('venues.links'));
    }

    public function test_team_members_are_paginated(): void
    {
        User::factory()->count(30)->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($this->organizer())
            ->get(route('admin.team'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/team')
                ->has('members.data', self::PER_PAGE)
                ->has('members.links'));
    }

    public function test_admin_events_list_is_paginated_but_overview_is_a_capped_summary(): void
    {
        // 30 eventos, cada um com sessão e assentos (para exercitar o agregado de ocupação).
        for ($i = 1; $i <= 30; $i++) {
            $this->makeSession(1);
        }
        $this->assertSame(30, Event::count());

        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->get(route('admin.events'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/events')
                ->has('events.data', self::PER_PAGE)
                ->has('events.links'));

        // Overview: resumo com no máximo 8 linhas, sem envelope de paginator.
        $this->actingAs($organizer)
            ->get(route('painel'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/overview')
                ->has('events', 8)
                ->where('kpis.events', 30));
    }

    public function test_paginated_event_rows_still_report_capacity_and_sold(): void
    {
        $session = $this->makeSession(3);

        $this->actingAs($this->organizer())
            ->get(route('admin.events'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('events.data.0.capacity', 3)
                ->where('events.data.0.sold', 0)
                ->where('events.data.0.sessionsCount', 1));

        $this->assertNotNull($session->id);
    }
}
