<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Com o OAuth desligado, a credencial salva do Mercado Pago é da conta da
 * organização principal (a de antes do multi-tenant): outra organização não
 * pode vender com ela, senão o dinheiro cairia na conta de outra pessoa.
 */
class LegacyCredentialsTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kena.mercadopago.oauth.enabled' => false]);
        Setting::put('mp_access_token', 'APP_USR-PRINCIPAL');
        Setting::put('mp_public_key', 'APP_USR-PRINCIPAL-PK');
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
    }

    public function test_only_principal_organization_gets_saved_credentials(): void
    {
        $this->assertSame('APP_USR-PRINCIPAL', MercadoPagoSettings::accessToken(Organization::DEFAULT_ID));
        $this->assertSame('APP_USR-PRINCIPAL', MercadoPagoSettings::accessToken());
        $this->assertTrue(MercadoPagoSettings::hasAccessToken(Organization::DEFAULT_ID));

        $this->assertNull(MercadoPagoSettings::accessToken($this->b->id));
        $this->assertNull(MercadoPagoSettings::publicKey($this->b->id));
        $this->assertFalse(MercadoPagoSettings::hasAccessToken($this->b->id));
        $this->assertNull(CurrentOrganization::run($this->b->id, fn () => MercadoPagoSettings::accessToken()));
    }

    public function test_other_organization_cannot_sell_without_its_own_account(): void
    {
        $this->assertTrue(Organization::findOrFail(Organization::DEFAULT_ID)->canSell());
        $this->assertFalse($this->b->canSell());
        $this->assertSame([Organization::DEFAULT_ID], Organization::query()->selling()->pluck('id')->all());

        $sessionB = CurrentOrganization::run($this->b->id, fn (): EventSession => $this->makeSession(2, 10000));
        $this->assertFalse($sessionB->fresh()->isSellable());
    }

    public function test_existing_payments_keep_using_saved_credentials(): void
    {
        $this->assertSame('APP_USR-PRINCIPAL', CurrentOrganization::run(
            $this->b->id,
            fn () => MercadoPagoSettings::accessTokenForPayment('123'),
        ));
    }

    public function test_principal_organization_takes_the_configured_owner_name(): void
    {
        DB::table('organizations')->where('id', Organization::DEFAULT_ID)
            ->update(['name' => Organization::DEFAULT_NAME, 'slug' => 'principal', 'contact_email' => null]);
        // O organizador mais antigo não é a dona: vale o e-mail configurado.
        PanelUser::factory()->create(['name' => 'Outra Pessoa', 'role' => PanelUser::ROLE_ORGANIZER, 'organization_id' => Organization::DEFAULT_ID]);
        PanelUser::factory()->create(['name' => 'Vitória Souza', 'email' => 'Vitoria@Exemplo.test', 'role' => PanelUser::ROLE_ORGANIZER, 'organization_id' => Organization::DEFAULT_ID]);

        $migration = require database_path('migrations/2026_11_27_000001_name_principal_organization.php');

        // Sem a variável, não mexe.
        $migration->up();
        $this->assertSame(Organization::DEFAULT_NAME, Organization::findOrFail(Organization::DEFAULT_ID)->name);

        config(['kena.principal_owner_email' => ' vitoria@exemplo.test ']);
        $migration->up();

        $principal = Organization::findOrFail(Organization::DEFAULT_ID);
        $this->assertSame('Vitória Souza', $principal->name);
        $this->assertSame('vitoria-souza', $principal->slug);
        $this->assertSame('vitoria@exemplo.test', $principal->contact_email);

        // Já renomeada: rodar de novo não mexe.
        $principal->update(['name' => 'Produtora da Vitória']);
        $migration->up();
        $this->assertSame('Produtora da Vitória', $principal->fresh()->name);
    }

    public function test_buyers_page_only_lists_buyers_of_own_organization(): void
    {
        $sessionA = $this->makeSession(1, 10000);
        $sessionB = CurrentOrganization::run($this->b->id, fn (): EventSession => $this->makeSession(1, 10000));
        $buyerA = User::factory()->create(['name' => 'Compradora A', 'cpf' => '52998224725']);
        $buyerB = User::factory()->create(['name' => 'Comprador B', 'cpf' => '11144477735']);
        Order::withoutGlobalScopes()->forceCreate($this->orderRow($sessionA, $buyerA, Organization::DEFAULT_ID));
        Order::withoutGlobalScopes()->forceCreate($this->orderRow($sessionB, $buyerB, $this->b->id));

        $organizerB = PanelUser::factory()->create(['role' => PanelUser::ROLE_ORGANIZER, 'organization_id' => $this->b->id]);

        $this->actingAs($organizerB, 'painel')->get('/painel/compradores')
            ->assertOk()
            ->assertSee('Comprador B')
            ->assertDontSee('Compradora A');

        $this->actingAs($organizerB, 'painel')
            ->delete("/painel/compradores/{$buyerA->id}/cpf")
            ->assertNotFound();
        $this->assertSame('52998224725', $buyerA->fresh()->cpf);
    }

    /** @return array<string, mixed> */
    private function orderRow(EventSession $session, User $user, int $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'user_id' => $user->id,
            'session_id' => $session->id,
            'status' => Order::STATUS_PAID,
            'subtotal_cents' => 10000,
            'fee_cents' => 0,
            'discount_cents' => 0,
            'total_cents' => 10000,
            'reference' => 'KNA-T'.$user->id,
        ];
    }
}
