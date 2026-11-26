<?php

namespace Tests\Feature\Kena;

use App\Models\MercadoPagoAccount;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\MercadoPagoOAuth;
use App\Services\SeatReservationService;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** "Conectar com Mercado Pago" (OAuth) — desligado por padrão. */
class MercadoPagoOAuthTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'kena.mercadopago.oauth.enabled' => true,
            'kena.mercadopago.oauth.client_id' => 'APP-123',
            'kena.mercadopago.oauth.client_secret' => 'SECRET-xyz',
            'kena.mercadopago.oauth.pkce' => true,
        ]);
    }

    private function organizer(): PanelUser
    {
        return PanelUser::factory()->create();
    }

    /** @param array<string, mixed> $overrides */
    private function account(array $overrides = []): MercadoPagoAccount
    {
        return MercadoPagoAccount::create($overrides + [
            'mp_user_id' => '111',
            'nickname' => 'ORGANIZADOR',
            'access_token' => 'APP_USR-ORG-TOKEN',
            'refresh_token' => 'TG-REFRESH',
            'public_key' => 'APP_USR-ORG-PK',
            'expires_at' => now()->addDays(150),
            'connected_at' => now(),
        ]);
    }

    public function test_routes_do_not_exist_while_disabled(): void
    {
        $user = $this->organizer();

        $this->actingAs($user, 'painel')->get('/painel/config/mercadopago/conectar')->assertNotFound();
        $this->actingAs($user, 'painel')->get('/painel/config/mercadopago/callback?code=x&state=y')->assertNotFound();
        $this->actingAs($user, 'painel')->post('/painel/config/mercadopago/desconectar')->assertNotFound();
    }

    public function test_disabled_keeps_the_current_manual_credentials(): void
    {
        $this->account();
        Setting::put('mp_access_token', 'AT-PANEL');
        Setting::put('mp_public_key', 'PK-PANEL');

        $this->assertSame('AT-PANEL', MercadoPagoSettings::accessToken());
        $this->assertSame('PK-PANEL', MercadoPagoSettings::publicKey());
        $this->assertNull(MercadoPagoSettings::currentAccountId());
    }

    public function test_staff_cannot_connect(): void
    {
        $this->enable();

        $this->actingAs(PanelUser::factory()->staff()->create(), 'painel')
            ->get('/painel/config/mercadopago/conectar')
            ->assertRedirect(route('admin.checkin'));
    }

    public function test_connect_redirects_to_mercado_pago_with_state_and_pkce(): void
    {
        $this->enable();

        $response = $this->actingAs($this->organizer(), 'painel')->get('/painel/config/mercadopago/conectar');

        $attempt = session('mp_oauth_attempt');
        $this->assertIsArray($attempt);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://auth.mercadopago.com/authorization?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('APP-123', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame($attempt['state'], $query['state']);
        $this->assertSame(route('admin.settings.mp.callback'), $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(MercadoPagoOAuth::challenge($attempt['verifier']), $query['code_challenge']);
        // O segredo da aplicação nunca vai na URL.
        $this->assertStringNotContainsString('SECRET-xyz', $location);
    }

    public function test_pkce_challenge_matches_rfc_7636_example(): void
    {
        $this->assertSame(
            'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            MercadoPagoOAuth::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
        );
    }

    public function test_callback_with_wrong_state_is_rejected(): void
    {
        $this->enable();
        Http::fake();

        $this->actingAs($this->organizer(), 'painel')
            ->withSession(['mp_oauth_attempt' => ['state' => 'certo', 'verifier' => 'v']])
            ->get('/painel/config/mercadopago/callback?code=TG-abc&state=errado')
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('mp_oauth.type', 'error');

        Http::assertNothingSent();
        $this->assertSame(0, MercadoPagoAccount::count());
    }

    public function test_callback_exchanges_code_and_stores_encrypted_tokens(): void
    {
        $this->enable();
        $old = $this->account(['mp_user_id' => '999', 'connected_at' => now()->subDay()]);
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'APP_USR-NEW-TOKEN',
                'token_type' => 'bearer',
                'expires_in' => 15552000,
                'scope' => 'offline_access payments write',
                'user_id' => 222,
                'refresh_token' => 'TG-NEW-REFRESH',
                'public_key' => 'APP_USR-NEW-PK',
                'live_mode' => true,
            ]),
            '*/users/me' => Http::response(['id' => 222, 'nickname' => 'TEATRO']),
        ]);
        $user = $this->organizer();

        $this->actingAs($user, 'painel')
            ->withSession(['mp_oauth_attempt' => ['state' => 'abc', 'verifier' => 'verifier-123']])
            ->get('/painel/config/mercadopago/callback?code=TG-code&state=abc')
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('mp_oauth.type', 'success');

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/oauth/token')
            && $r['grant_type'] === 'authorization_code'
            && $r['code'] === 'TG-code'
            && $r['code_verifier'] === 'verifier-123'
            && $r['client_secret'] === 'SECRET-xyz'
            && $r['redirect_uri'] === route('admin.settings.mp.callback'));

        $account = MercadoPagoAccount::current();
        $this->assertNotNull($account);
        $this->assertSame('222', $account->mp_user_id);
        $this->assertSame('TEATRO', $account->nickname);
        $this->assertSame($user->id, $account->connected_by);
        $this->assertTrue($account->expires_at->isAfter(now()->addDays(179)));
        // Encriptado em repouso.
        $raw = MercadoPagoAccount::query()->toBase()->where('id', $account->id)->first();
        $this->assertStringNotContainsString('APP_USR-NEW-TOKEN', (string) $raw->access_token);
        $this->assertStringNotContainsString('TG-NEW-REFRESH', (string) $raw->refresh_token);
        // A conta anterior para de receber vendas novas.
        $this->assertNotNull($old->fresh()?->disconnected_at);

        $this->assertSame('APP_USR-NEW-TOKEN', MercadoPagoSettings::accessToken());
        $this->assertSame('APP_USR-NEW-PK', MercadoPagoSettings::publicKey());
    }

    public function test_callback_handles_refused_code(): void
    {
        $this->enable();
        Http::fake(['*/oauth/token' => Http::response(['error' => 'invalid_grant', 'message' => 'invalid code'], 400)]);

        $this->actingAs($this->organizer(), 'painel')
            ->withSession(['mp_oauth_attempt' => ['state' => 'abc', 'verifier' => 'v']])
            ->get('/painel/config/mercadopago/callback?code=TG-old&state=abc')
            ->assertSessionHas('mp_oauth.type', 'error');

        $this->assertSame(0, MercadoPagoAccount::count());
    }

    public function test_enabled_ignores_manual_token_and_requires_a_connected_account(): void
    {
        $this->enable();
        Setting::put('mp_access_token', 'AT-PANEL');
        Setting::put('mp_public_key', 'PK-PANEL');
        config(['kena.mercadopago.access_token' => 'AT-ENV']);

        $this->assertNull(MercadoPagoSettings::accessToken());
        $this->assertNull(MercadoPagoSettings::publicKey());

        $this->account();
        $this->assertSame('APP_USR-ORG-TOKEN', MercadoPagoSettings::accessToken());
    }

    public function test_panel_does_not_save_manual_token_when_enabled(): void
    {
        $this->enable();

        $this->actingAs($this->organizer(), 'painel')->post('/painel/config', [
            'mp_access_token' => 'APP_USR-MANUAL',
            'mp_public_key' => 'APP_USR-MANUAL-PK',
        ])->assertRedirect();

        $this->assertNull(Setting::get('mp_access_token'));
        $this->assertNull(Setting::get('mp_public_key'));
    }

    public function test_old_payment_keeps_using_the_account_that_created_it(): void
    {
        $this->enable();
        $first = $this->account();
        $order = $this->paidOrder();
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'mercadopago_account_id' => $first->id,
            'method' => 'card', 'status' => 'approved', 'amount_cents' => $order->total_cents, 'gateway_payment_id' => '555',
        ]);
        $first->update(['disconnected_at' => now()]);
        $this->account(['mp_user_id' => '333', 'access_token' => 'APP_USR-SECOND']);

        $this->assertSame('APP_USR-ORG-TOKEN', MercadoPagoSettings::accessTokenForPayment('555'));
        $this->assertSame('APP_USR-SECOND', MercadoPagoSettings::accessToken());
    }

    public function test_expired_token_is_refreshed_before_use(): void
    {
        $this->enable();
        $this->account(['expires_at' => now()->subMinute()]);
        Http::fake(['*/oauth/token' => Http::response([
            'access_token' => 'APP_USR-RENEWED', 'refresh_token' => 'TG-NEXT', 'user_id' => 111, 'expires_in' => 15552000,
        ])]);

        $this->assertSame('APP_USR-RENEWED', MercadoPagoSettings::accessToken());
        $this->assertSame('TG-NEXT', MercadoPagoAccount::current()?->refresh_token);
        Http::assertSent(fn (Request $r): bool => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'TG-REFRESH');
    }

    public function test_refresh_command_renews_tokens_close_to_expiring(): void
    {
        $this->enable();
        $soon = $this->account(['expires_at' => now()->addDays(10)]);
        $later = $this->account(['mp_user_id' => '444', 'expires_at' => now()->addDays(120), 'access_token' => 'APP_USR-LATER']);
        Http::fake(['*/oauth/token' => Http::response([
            'access_token' => 'APP_USR-RENEWED', 'refresh_token' => 'TG-NEXT', 'user_id' => 111, 'expires_in' => 15552000,
        ])]);

        $this->artisan('kena:refresh-mp-tokens')->assertSuccessful();

        $this->assertSame('APP_USR-RENEWED', $soon->fresh()?->access_token);
        $this->assertSame('APP_USR-LATER', $later->fresh()?->access_token);
        Http::assertSentCount(1);
    }

    public function test_gateway_sends_service_fee_as_application_fee_only_with_oauth(): void
    {
        Http::fake(['*/v1/payments' => Http::response(['id' => 1, 'status' => 'approved'])]);
        $order = $this->paidOrder();
        $this->assertGreaterThan(0, $order->fee_cents);

        // Desligado: nada muda no corpo.
        config(['kena.mercadopago.access_token' => 'TEST-token']);
        app(MercadoPagoGateway::class)->createPix($order, 'p@x.test', '52998224725');
        Http::assertSent(fn (Request $r): bool => ! array_key_exists('application_fee', $r->data()));

        // Ligado: cobra com o token do organizador e separa a taxa.
        $this->enable();
        $this->account();
        app(MercadoPagoGateway::class)->createPix($order, 'p@x.test', '52998224725');
        Http::assertSent(fn (Request $r): bool => $r->hasHeader('Authorization', 'Bearer APP_USR-ORG-TOKEN')
            && ($r->data()['application_fee'] ?? null) === round($order->fee_cents / 100, 2));
    }

    public function test_disconnect_blocks_new_sales(): void
    {
        $this->enable();
        $this->account();

        $this->actingAs($this->organizer(), 'painel')
            ->post('/painel/config/mercadopago/desconectar')
            ->assertRedirect(route('admin.settings'));

        $this->assertNull(MercadoPagoAccount::current());
        $this->assertNull(MercadoPagoSettings::accessToken());
    }

    private function paidOrder(): Order
    {
        config(['kena.service_fee_percent' => 6]);
        $session = $this->makeSession(1);
        $reservation = app(SeatReservationService::class)->hold($session, User::factory()->create(), $this->availableSeatIds($session, 1));

        return app(OrderService::class)->createFromReservation($reservation);
    }
}
