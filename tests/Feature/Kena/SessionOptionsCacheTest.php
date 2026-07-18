<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\PanelUser;
use App\Support\SessionOptionsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * O seletor de sessões da tela de pedidos é cacheado. Estes testes garantem que
 * ele nunca serve dado obsoleto — sem invalidação, uma sessão nova só apareceria
 * depois do TTL expirar.
 */
class SessionOptionsCacheTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    public function test_creating_a_session_invalidates_the_dropdown(): void
    {
        $session = $this->makeSession(1);
        $organizer = $this->organizer();

        // Primeira visita popula o cache.
        $this->actingAs($organizer, 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->component('admin/orders')->has('sessions', 1));

        EventSession::create([
            'event_id' => $session->event_id,
            'starts_at' => now()->addDays(45),
            'status' => 'on_sale',
        ]);

        $this->actingAs($organizer, 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->has('sessions', 2));
    }

    public function test_deleting_a_session_invalidates_the_dropdown(): void
    {
        $session = $this->makeSession(1);
        $organizer = $this->organizer();

        $this->actingAs($organizer, 'painel')->get(route('admin.orders'));

        $session->delete();

        $this->actingAs($organizer, 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->has('sessions', 0));
    }

    public function test_renaming_the_event_relabels_the_dropdown(): void
    {
        $session = $this->makeSession(1);
        $organizer = $this->organizer();

        $this->actingAs($organizer, 'painel')->get(route('admin.orders'));

        $session->event->update(['title' => 'Título Renomeado']);

        $this->actingAs($organizer, 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->where(
                'sessions.0.label',
                fn (string $label): bool => str_contains($label, 'Título Renomeado'),
            ));
    }

    public function test_options_are_actually_cached_between_requests(): void
    {
        $this->makeSession(1);
        $organizer = $this->organizer();

        Cache::forget(SessionOptionsCache::KEY);
        $this->assertFalse(Cache::has(SessionOptionsCache::KEY));

        $this->actingAs($organizer, 'painel')->get(route('admin.orders'));

        $this->assertTrue(Cache::has(SessionOptionsCache::KEY));
    }
}
