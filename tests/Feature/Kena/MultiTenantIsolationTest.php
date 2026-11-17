<?php

namespace Tests\Feature\Kena;

use App\Models\CheckIn;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PhotoGallery;
use App\Models\User;
use App\Models\Venue;
use App\Services\CheckInService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Vazamento entre organizadores: o organizador A tenta ver, editar, exportar,
 * estornar e escanear dados do B pelo painel. Tem que falhar sempre.
 */
class MultiTenantIsolationTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private PanelUser $orgA;

    private Organization $b;

    private EventSession $sessionB;

    private Order $orderB;

    private Coupon $couponB;

    private PanelUser $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        // A = organização principal (a de hoje).
        $sessionA = $this->makeSession(2);
        $this->paidOrder($sessionA);
        $this->orgA = PanelUser::factory()->create();

        // B = outro organizador, com evento, venda paga, cupom, galeria e equipe.
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        CurrentOrganization::run($this->b->id, function (): void {
            $this->sessionB = $this->makeSession(2);
            $this->sessionB->event->update(['title' => 'Espetaculo Secreto B']);
            $this->orderB = $this->paidOrder($this->sessionB);
            $this->couponB = Coupon::create(['code' => 'SEGREDOB', 'type' => 'percent', 'value' => 10, 'active' => true]);
            PhotoGallery::create(['event_id' => $this->sessionB->event_id, 'status' => 'draft', 'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]]]);
        });
        $this->staffB = PanelUser::factory()->forOrganization($this->b)->create(['email' => 'equipe-b@teatro.test']);
    }

    private function paidOrder(EventSession $session): Order
    {
        $reservation = app(SeatReservationService::class)
            ->hold($session, User::factory()->create(), $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order;
    }

    public function test_data_is_created_in_the_right_organization(): void
    {
        $this->assertSame($this->b->id, $this->sessionB->event->organization_id);
        $this->assertSame($this->b->id, $this->orderB->organization_id);
        $this->assertSame($this->b->id, $this->couponB->organization_id);
        $this->assertSame(Organization::DEFAULT_ID, $this->orgA->organization_id);
    }

    public function test_lists_do_not_show_other_organization(): void
    {
        $as = $this->actingAs($this->orgA, 'painel');

        foreach (['/painel', '/painel/eventos', '/painel/pedidos', '/painel/cupons', '/painel/locais', '/painel/equipe', '/painel/fotos', '/painel/cortesias'] as $url) {
            $body = (string) $as->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('Espetaculo Secreto B', $body, $url);
            $this->assertStringNotContainsString($this->orderB->reference, $body, $url);
            $this->assertStringNotContainsString('SEGREDOB', $body, $url);
            $this->assertStringNotContainsString('equipe-b@teatro.test', $body, $url);
        }
    }

    public function test_overview_counts_only_own_revenue(): void
    {
        $this->actingAs($this->orgA, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.events', 1)
                ->where('kpis.sessions', 1)
                ->where('kpis.capacity', 2)
                ->where('kpis.sold', 1));
    }

    public function test_direct_links_to_other_organization_are_not_found(): void
    {
        $as = $this->actingAs($this->orgA, 'painel');
        $eventB = $this->sessionB->event_id;
        $venueB = $this->sessionB->event->venue_id;

        $as->get("/painel/eventos/{$eventB}/editar")->assertNotFound();
        $as->put("/painel/eventos/{$eventB}", [])->assertNotFound();
        $as->delete("/painel/eventos/{$eventB}")->assertNotFound();
        $as->get("/painel/locais/{$venueB}/editar")->assertNotFound();
        $as->delete("/painel/locais/{$venueB}")->assertNotFound();
        $as->get("/painel/sessoes/{$this->sessionB->id}/assentos")->assertNotFound();
        $as->get("/painel/sessoes/{$this->sessionB->id}/lotes")->assertNotFound();
        $as->post("/painel/sessoes/{$this->sessionB->id}/cancelar")->assertNotFound();
        $as->get("/painel/cupons/{$this->couponB->id}/editar")->assertNotFound();
        $as->delete("/painel/cupons/{$this->couponB->id}")->assertNotFound();
        $as->post("/painel/pedidos/{$this->orderB->id}/reembolso")->assertNotFound();
        $as->get("/painel/fotos/{$eventB}")->assertNotFound();
        $as->put("/painel/equipe/{$this->staffB->id}", ['name' => 'x', 'role' => 'staff'])->assertNotFound();
        $as->delete("/painel/equipe/{$this->staffB->id}")->assertNotFound();

        $this->assertSame(Order::STATUS_PAID, $this->orderB->fresh()?->status);
        $this->assertNotNull(PanelUser::find($this->staffB->id));
    }

    public function test_export_does_not_include_other_organization(): void
    {
        $csv = (string) $this->actingAs($this->orgA, 'painel')->get('/painel/pedidos/export')->assertOk()->streamedContent();

        $this->assertStringNotContainsString($this->orderB->reference, $csv);
        $this->assertStringNotContainsString($this->orderB->user->email, $csv);
    }

    public function test_cannot_use_other_organization_ids_in_forms(): void
    {
        $as = $this->actingAs($this->orgA, 'painel');

        $as->post('/painel/eventos', [
            'venue_id' => $this->sessionB->event->venue_id,
            'title' => 'Invasor', 'kicker' => 'x', 'description' => 'x', 'status' => 'draft',
            'banner_from' => '#000000', 'banner_to' => '#000000', 'sector_name' => 'Plateia', 'price' => 10,
            'sessions' => [['starts_at' => now()->addMonth()->toDateTimeString()]],
        ])->assertSessionHasErrors('venue_id');

        $as->post('/painel/cupons', [
            'code' => 'INVASOR', 'type' => 'percent', 'value' => 10, 'event_id' => $this->sessionB->event_id,
        ])->assertSessionHasErrors('event_id');

        $this->assertFalse(Event::withoutGlobalScopes()->where('title', 'Invasor')->exists());
    }

    public function test_same_coupon_code_in_two_organizations_only_applies_to_own_events(): void
    {
        $this->actingAs($this->orgA, 'painel')->post('/painel/cupons', [
            'code' => 'SEGREDOB', 'type' => 'percent', 'value' => 50, 'active' => true,
        ])->assertSessionHasNoErrors();

        $couponA = Coupon::withoutGlobalScopes()->where('organization_id', Organization::DEFAULT_ID)->where('code', 'SEGREDOB')->firstOrFail();
        $eventA = Event::withoutGlobalScopes()->where('organization_id', Organization::DEFAULT_ID)->value('id');

        $this->assertSame($couponA->id, app(CouponService::class)->resolve('SEGREDOB', $eventA)?->id);
        $this->assertSame($this->couponB->id, app(CouponService::class)->resolve('SEGREDOB', $this->sessionB->event_id)?->id);
    }

    public function test_check_in_rejects_ticket_from_other_organization(): void
    {
        $ticketB = $this->orderB->tickets()->firstOrFail();
        $sessionA = EventSession::withoutGlobalScopes()->whereHas('event', fn ($q) => $q->withoutGlobalScopes()->where('organization_id', Organization::DEFAULT_ID))->firstOrFail();
        $staffA = PanelUser::factory()->staff()->create();
        $as = $this->actingAs($staffA, 'painel');

        // Sessão do B: nem existe para a equipe do A.
        $as->postJson('/api/checkin', ['session_id' => $this->sessionB->id, 'token' => $ticketB->qr_token])->assertNotFound();

        // Ingresso do B escaneado na sessão do A: negado, e o B não é marcado.
        $as->postJson('/api/checkin', ['session_id' => $sessionA->id, 'token' => $ticketB->qr_token])
            ->assertOk()
            ->assertJsonPath('result', CheckIn::RESULT_DENIED);
        $this->assertNull($ticketB->fresh()?->checked_in_at);
    }

    public function test_other_organization_check_in_still_works_for_its_own_staff(): void
    {
        $ticketB = $this->orderB->tickets()->firstOrFail();

        $this->actingAs($this->staffB, 'painel')
            ->postJson('/api/checkin', ['session_id' => $this->sessionB->id, 'token' => $ticketB->qr_token])
            ->assertOk()
            ->assertJsonPath('result', CheckIn::RESULT_OK);
    }

    public function test_context_is_cleared_after_the_request(): void
    {
        $this->actingAs($this->orgA, 'painel')->get('/painel/eventos')->assertOk();

        $this->assertNull(CurrentOrganization::id());
        $this->assertSame(2, Event::count());
        $this->assertSame(2, Venue::count());
    }

    public function test_service_catalog_lists_every_organization_publicly(): void
    {
        $this->get('/eventos')->assertOk()->assertSee('Espetaculo Secreto B');
    }

    public function test_check_in_service_outside_panel_is_not_filtered(): void
    {
        // Fila/comandos (sem painel) continuam enxergando tudo.
        $ticketB = $this->orderB->tickets()->firstOrFail();
        $result = app(CheckInService::class)->check($ticketB->qr_token, $this->sessionB, $this->staffB);

        $this->assertSame(CheckIn::RESULT_OK, $result['result']);
    }
}
