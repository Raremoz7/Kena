<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Área do super admin (/admin): conta própria, visão de todos, "entrar como". */
class SuperAdminTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private PlatformAdmin $admin;

    private Organization $b;

    private PanelUser $ownerB;

    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::factory()->create(['name' => 'Somo', 'email' => 'somo@somo.test']);

        $this->paidOrder($this->makeSession(1));
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        CurrentOrganization::run($this->b->id, function (): void {
            $session = $this->makeSession(1);
            $session->event->update(['title' => 'Espetaculo do B']);
            $this->orderB = $this->paidOrder($session);
        });
        $this->ownerB = PanelUser::factory()->forOrganization($this->b)->create(['email' => 'dono@teatrob.test']);
    }

    private function paidOrder(EventSession $session): Order
    {
        $reservation = app(SeatReservationService::class)
            ->hold($session, User::factory()->create(['email' => 'comprador'.$session->id.'@x.test']), $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);

        return $order;
    }

    public function test_login_is_separate_from_the_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        // Conta do painel não entra no /admin, nem com e-mail e senha.
        PanelUser::factory()->create(['email' => 'org@x.test']);
        $this->post('/admin/login', ['email' => 'org@x.test', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs(PanelUser::factory()->create(), 'painel')->get('/admin/organizacoes')->assertRedirect('/admin/login');

        $this->post('/admin/login', ['email' => 'somo@somo.test', 'password' => 'password'])->assertRedirectContains('/admin');
        $this->assertAuthenticatedAs($this->admin, 'admin');
    }

    public function test_super_admin_sees_every_organization_event_and_order(): void
    {
        $as = $this->actingAs($this->admin, 'admin');

        $as->get('/admin/organizacoes')->assertInertia(fn (Assert $p) => $p->component('super/organizations')->has('organizations', 2));
        $as->get('/admin/eventos')->assertInertia(fn (Assert $p) => $p->component('super/events')->where('events.meta.total', 2));
        $as->get('/admin/pedidos')->assertInertia(fn (Assert $p) => $p->component('super/orders')->where('orders.meta.total', 2));
        $as->get('/admin/pedidos?q='.$this->orderB->reference)->assertInertia(fn (Assert $p) => $p
            ->where('orders.meta.total', 1)
            ->where('orders.data.0.organization', 'Teatro B'));
        $as->get('/admin/pedidos?organizacao='.$this->b->id)->assertInertia(fn (Assert $p) => $p->where('orders.meta.total', 1));
        $as->get('/admin/eventos?q=Teatro B')->assertInertia(fn (Assert $p) => $p->where('events.data.0.title', 'Espetaculo do B'));
    }

    public function test_financial_dashboard_sums_every_organization(): void
    {
        $orders = Order::query()->where('status', Order::STATUS_PAID)->get();

        $this->actingAs($this->admin, 'admin')->get('/admin?periodo=30d')
            ->assertInertia(fn (Assert $p) => $p
                ->component('super/dashboard')
                ->where('kpis.orders', 2)
                ->where('kpis.gross', fn ($v): bool => (float) $v === round($orders->sum('total_cents') / 100, 2))
                ->where('kpis.fees', fn ($v): bool => (float) $v === round($orders->sum('fee_cents') / 100, 2))
                ->has('byOrganization', 2)
                ->has('months', 12));
    }

    public function test_suspend_reactivate_and_fee_are_audited(): void
    {
        $as = $this->actingAs($this->admin, 'admin');
        config(['kena.service_fee_percent' => 6]);

        $as->post("/admin/organizacoes/{$this->b->id}/suspender", [])->assertSessionHasErrors('reason');
        $as->post("/admin/organizacoes/{$this->b->id}/suspender", ['reason' => 'Denúncia']);
        $this->assertTrue($this->b->fresh()?->isSuspended());
        $as->post("/admin/organizacoes/{$this->b->id}/reativar");
        $this->assertFalse($this->b->fresh()?->isSuspended());
        $as->put("/admin/organizacoes/{$this->b->id}/taxa", ['fee_percent' => 4.5]);
        $this->assertSame(4.5, $this->b->fresh()?->serviceFeePercent());
        $as->put("/admin/organizacoes/{$this->b->id}/taxa", ['fee_percent' => 50])->assertSessionHasErrors('fee_percent');

        $this->assertSame(
            ['organization.suspend', 'organization.reactivate', 'organization.fee'],
            PlatformAuditLog::query()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_impersonation_opens_the_organizer_panel_and_is_logged(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post("/admin/organizacoes/{$this->b->id}/entrar")
            ->assertRedirect('/painel');

        $this->assertAuthenticatedAs($this->ownerB, 'painel');

        // No painel: só os dados do B, com o aviso fixo.
        $this->get('/painel/eventos')
            ->assertSee('Espetaculo do B')
            ->assertInertia(fn (Assert $p) => $p
                ->where('impersonation.organizationName', 'Teatro B')
                ->where('impersonation.adminName', 'Somo')
                ->has('events.data', 1));

        $this->post('/painel/sair-do-modo-admin')->assertRedirect('/admin/organizacoes');
        $this->assertGuest('painel');
        $this->assertAuthenticatedAs($this->admin, 'admin');

        $logs = PlatformAuditLog::query()->orderBy('id')->get();
        $this->assertSame(['impersonate.start', 'impersonate.stop'], $logs->pluck('action')->all());
        $this->assertSame($this->b->id, $logs[0]->organization_id);
        $this->assertSame($this->admin->id, $logs[0]->platform_admin_id);
    }

    public function test_organizer_cannot_fake_an_exit_or_impersonate(): void
    {
        $this->actingAs($this->ownerB, 'painel')->post('/painel/sair-do-modo-admin')->assertNotFound();
        $this->actingAs($this->ownerB, 'painel')->post("/admin/organizacoes/{$this->b->id}/entrar")->assertRedirect('/admin/login');
        $this->assertSame(0, PlatformAuditLog::count());
    }

    public function test_admin_logout_also_ends_impersonation(): void
    {
        $this->actingAs($this->admin, 'admin')->post("/admin/organizacoes/{$this->b->id}/entrar");

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
        $this->assertGuest('painel');
    }

    public function test_organization_without_organizer_cannot_be_impersonated(): void
    {
        $empty = Organization::create(['name' => 'Vazia', 'slug' => 'vazia']);

        $this->actingAs($this->admin, 'admin')->post("/admin/organizacoes/{$empty->id}/entrar")
            ->assertSessionHas('error');
        $this->assertGuest('painel');
    }

    public function test_command_creates_and_removes_super_admin(): void
    {
        $this->artisan('kena:super-admin', ['email' => 'nova@somo.test', '--name' => 'Nova'])
            ->expectsQuestion('Senha (mínimo 8, com letra e número)', 'senha1234')
            ->assertSuccessful();
        $this->assertTrue(PlatformAdmin::where('email', 'nova@somo.test')->exists());

        $this->artisan('kena:super-admin', ['email' => 'nova@somo.test', '--remove' => true])->assertSuccessful();
        $this->assertFalse(PlatformAdmin::where('email', 'nova@somo.test')->exists());
    }

    public function test_panel_no_longer_has_platform_pages(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')->get('/painel/plataforma')->assertNotFound();
        $this->assertSame(2, Event::count());
    }
}
