<?php

namespace Tests\Feature\Kena;

use App\Exceptions\PaymentException;
use App\Models\EventSession;
use App\Models\MercadoPagoAccount;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\MercadoPagoOAuth;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Support\CurrentOrganization;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** A venda cai na conta e na taxa do organizador dono do evento. */
class MultiTenantPaymentTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private Organization $b;

    private EventSession $sessionA;

    private EventSession $sessionB;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'kena.service_fee_percent' => 6,
            'kena.mercadopago.oauth.enabled' => true,
            'kena.mercadopago.oauth.client_id' => 'APP-1',
            'kena.mercadopago.oauth.client_secret' => 'SECRET',
        ]);

        $this->sessionA = $this->makeSession(2, 10000);
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b', 'service_fee_percent' => 4]);
        $this->sessionB = CurrentOrganization::run($this->b->id, fn (): EventSession => $this->makeSession(2, 10000));
    }

    private function connect(int $organizationId, string $token, string $userId): MercadoPagoAccount
    {
        return MercadoPagoAccount::create([
            'organization_id' => $organizationId, 'mp_user_id' => $userId, 'access_token' => $token,
            'public_key' => $token.'-PK', 'connected_at' => now(), 'expires_at' => now()->addDays(100),
        ]);
    }

    private function order(EventSession $session): Order
    {
        $reservation = app(SeatReservationService::class)
            ->hold($session, User::factory()->create(), $this->availableSeatIds($session, 1));

        return app(OrderService::class)->createFromReservation($reservation);
    }

    public function test_each_organization_charges_on_its_own_account_with_its_own_fee(): void
    {
        $this->connect(Organization::DEFAULT_ID, 'APP_USR-A', '100');
        $this->connect($this->b->id, 'APP_USR-B', '200');
        Http::fake(['*/v1/payments' => Http::response(['id' => 1, 'status' => 'pending'])]);

        $orderA = $this->order($this->sessionA);
        $orderB = $this->order($this->sessionB);
        $this->assertSame(600, $orderA->fee_cents); // 6% padrão
        $this->assertSame(400, $orderB->fee_cents); // 4% negociado

        app(MercadoPagoGateway::class)->createPix($orderB, 'p@x.test', '52998224725');

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer APP_USR-B')
            && ($r->data()['application_fee'] ?? null) === 4.0);
        Http::assertNotSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer APP_USR-A'));

        $this->assertSame('APP_USR-B-PK', MercadoPagoSettings::publicKey($this->b->id));
        $this->assertSame('APP_USR-A-PK', MercadoPagoSettings::publicKey(Organization::DEFAULT_ID));
    }

    public function test_organization_without_connected_account_cannot_sell(): void
    {
        $this->connect(Organization::DEFAULT_ID, 'APP_USR-A', '100');

        $this->assertTrue($this->sessionA->fresh()?->isSellable());
        $this->assertFalse($this->sessionB->fresh()?->isSellable());
    }

    public function test_suspended_organization_cannot_sell_or_be_paid(): void
    {
        $this->connect($this->b->id, 'APP_USR-B', '200');
        $order = $this->order($this->sessionB);
        $this->b->update(['status' => Organization::STATUS_SUSPENDED, 'suspended_at' => now()]);

        $this->assertFalse($this->sessionB->fresh()?->isSellable());

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('suspensas');
        app(PaymentService::class)->pay($order->reservation()->firstOrFail(), ['method' => 'pix']);
    }

    public function test_without_oauth_every_organization_uses_the_platform_credentials(): void
    {
        config(['kena.mercadopago.oauth.enabled' => false, 'kena.mercadopago.access_token' => 'TEST-PLATAFORMA']);

        $this->assertTrue($this->sessionB->fresh()?->isSellable());
        $this->assertSame('TEST-PLATAFORMA', MercadoPagoSettings::accessToken($this->b->id));
    }

    public function test_one_mercado_pago_account_cannot_serve_two_organizations(): void
    {
        $this->connect(Organization::DEFAULT_ID, 'APP_USR-A', '100');
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'APP_USR-NEW', 'user_id' => 100, 'expires_in' => 100]),
            '*/users/me' => Http::response(['nickname' => 'X']),
        ]);
        $organizerB = PanelUser::factory()->forOrganization($this->b)->create();

        $this->actingAs($organizerB, 'painel')
            ->withSession(['mp_oauth_attempt' => ['state' => 'abc', 'verifier' => null]])
            ->get('/painel/config/mercadopago/callback?code=TG&state=abc')
            ->assertSessionHas('mp_oauth.type', 'error');

        $account = MercadoPagoAccount::withoutGlobalScopes()->where('mp_user_id', '100')->firstOrFail();
        $this->assertSame(Organization::DEFAULT_ID, $account->organization_id);
        $this->assertSame('APP_USR-A', $account->access_token);
    }

    public function test_connecting_in_one_organization_does_not_disconnect_another(): void
    {
        $a = $this->connect(Organization::DEFAULT_ID, 'APP_USR-A', '100');
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'APP_USR-B', 'user_id' => 200, 'expires_in' => 100]),
            '*/users/me' => Http::response(['nickname' => 'B']),
        ]);

        CurrentOrganization::run($this->b->id, fn () => app(MercadoPagoOAuth::class)->connect('TG', null, null));

        $this->assertNull($a->fresh()?->disconnected_at);
        $this->assertSame('200', MercadoPagoAccount::forOrganization($this->b->id)?->mp_user_id);
    }
}
