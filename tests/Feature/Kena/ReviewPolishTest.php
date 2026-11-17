<?php

namespace Tests\Feature\Kena;

use App\Exceptions\PaymentException;
use App\Mail\MercadoPagoReconnectMail;
use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PlatformAdmin;
use App\Services\Payments\MercadoPagoOAuth;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Revisão final: Mercado Pago no "entrar como", token vencido, avisos, minha conta. */
class ReviewPolishTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private Organization $b;

    private PanelUser $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'kena.mercadopago.oauth.enabled' => true,
            'kena.mercadopago.oauth.client_id' => 'APP-1',
            'kena.mercadopago.oauth.client_secret' => 'S',
        ]);
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $this->ownerB = PanelUser::factory()->forOrganization($this->b)->create(['email' => 'dono@b.test']);
    }

    private function account(array $overrides = []): MercadoPagoAccount
    {
        return MercadoPagoAccount::create($overrides + [
            'organization_id' => $this->b->id, 'mp_user_id' => '77', 'access_token' => 'APP_USR-B',
            'refresh_token' => 'TG-B', 'connected_at' => now(), 'expires_at' => now()->addDays(100),
        ]);
    }

    public function test_super_admin_cannot_connect_or_disconnect_mercado_pago_while_impersonating(): void
    {
        $this->account();
        $this->actingAs(PlatformAdmin::factory()->create(), 'admin')->post("/admin/organizacoes/{$this->b->id}/entrar");

        $this->get('/painel/config/mercadopago/conectar')->assertSessionHas('mp_oauth.type', 'error');
        $this->post('/painel/config/mercadopago/desconectar')->assertSessionHas('mp_oauth.type', 'error');
        $this->assertNotNull(MercadoPagoAccount::forOrganization($this->b->id));

        $this->get('/painel/config')->assertInertia(fn (Assert $p) => $p->where('mpOauth.blocked', true));
    }

    public function test_failed_renewal_warns_once_and_stops_sales_when_expired(): void
    {
        Mail::fake();
        Http::fake(['*/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);
        $account = $this->account(['expires_at' => now()->addDays(5)]);

        // Renovação agendada falha: marca, avisa uma vez, ainda vende até vencer.
        $this->artisan('kena:refresh-mp-tokens')->assertSuccessful();
        $this->artisan('kena:refresh-mp-tokens')->assertSuccessful();
        Mail::assertQueued(MercadoPagoReconnectMail::class, 1);
        $this->assertTrue($account->fresh()?->needsReconnect());
        $this->assertTrue($this->b->fresh()?->canSell());

        $this->actingAs($this->ownerB, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p->where('organizationAlert.kind', 'warning')->where('organizationAlert.actionLabel', 'Reconectar'));

        // Venceu: para de vender, a tela de configurações não quebra.
        $this->travel(6)->days();
        $this->assertFalse($this->b->fresh()?->canSell());
        $this->actingAs($this->ownerB, 'painel')->get('/painel/config')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('mpOauth.account.needsReconnect', true));
        $this->actingAs($this->ownerB, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p->where('organizationAlert.kind', 'danger'));
    }

    public function test_reconnecting_clears_the_warning(): void
    {
        $this->account(['refresh_failed_at' => now()]);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'APP_USR-NOVO', 'user_id' => 77, 'expires_in' => 15552000, 'refresh_token' => 'TG-2']),
            '*/users/me' => Http::response(['nickname' => 'B']),
        ]);

        CurrentOrganization::run($this->b->id, fn () => app(MercadoPagoOAuth::class)->connect('TG', null, null));

        $this->assertFalse(MercadoPagoAccount::forOrganization($this->b->id)?->needsReconnect());
    }

    public function test_alert_for_missing_account_and_suspension_but_not_for_staff(): void
    {
        $this->actingAs($this->ownerB, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p->where('organizationAlert.actionLabel', 'Conectar'));

        $this->b->update(['status' => Organization::STATUS_SUSPENDED, 'suspended_reason' => 'Denúncia']);
        $this->actingAs($this->ownerB, 'painel')->get('/painel')
            ->assertInertia(fn (Assert $p) => $p
                ->where('organizationAlert.kind', 'danger')
                ->where('organizationAlert.message', fn ($m): bool => str_contains((string) $m, 'Denúncia')));

        $staff = PanelUser::factory()->forOrganization($this->b)->staff()->create();
        $this->actingAs($staff, 'painel')->get('/painel/checkin')
            ->assertInertia(fn (Assert $p) => $p->where('organizationAlert', null));
    }

    public function test_refresh_failure_on_payment_path_is_reported(): void
    {
        Mail::fake();
        Http::fake(['*/oauth/token' => Http::response([], 500)]);
        $account = $this->account(['expires_at' => now()->subMinute()]);

        try {
            app(MercadoPagoOAuth::class)->freshToken($account);
            $this->fail('Deveria falhar.');
        } catch (PaymentException) {
        }

        $this->assertNotNull($account->fresh()?->refresh_failed_at);
        Mail::assertQueued(MercadoPagoReconnectMail::class, fn ($m): bool => $m->hasTo('dono@b.test'));
    }

    public function test_my_account_changes_name_and_password(): void
    {
        $as = $this->actingAs($this->ownerB, 'painel');

        $as->get('/painel/conta')->assertInertia(fn (Assert $p) => $p->component('admin/account')->where('account.email', 'dono@b.test'));
        $as->put('/painel/conta', ['name' => 'Novo Nome'])->assertSessionHasNoErrors();
        $this->assertSame('Novo Nome', $this->ownerB->fresh()?->name);

        $as->put('/painel/conta/senha', ['current_password' => 'errada', 'password' => 'outra1234', 'password_confirmation' => 'outra1234'])
            ->assertSessionHasErrors('current_password');
        $as->put('/painel/conta/senha', ['current_password' => 'password', 'password' => 'outra1234', 'password_confirmation' => 'outra1234'])
            ->assertSessionHasNoErrors();

        $this->post('/painel/logout');
        $this->post('/painel/login', ['email' => 'dono@b.test', 'password' => 'outra1234'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->ownerB->fresh() ?? $this->ownerB, 'painel');
    }

    public function test_organizer_page_shows_contact(): void
    {
        config(['kena.mercadopago.oauth.enabled' => false]);
        $this->b->update(['contact_email' => 'oi@b.test']);

        $this->get('/o/teatro-b')->assertInertia(fn (Assert $p) => $p->where('organization.email', 'oi@b.test'));
    }
}
