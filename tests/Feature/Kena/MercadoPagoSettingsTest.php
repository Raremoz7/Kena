<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
use App\Models\Setting;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MercadoPagoSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_prefers_panel_over_env(): void
    {
        config([
            'kena.mercadopago.public_key' => 'PK-ENV',
            'kena.mercadopago.access_token' => 'AT-ENV',
            'kena.mercadopago.pix_expiration_minutes' => 30,
        ]);

        // Sem nada salvo → cai para o .env/config.
        $this->assertSame('PK-ENV', MercadoPagoSettings::publicKey());
        $this->assertSame('AT-ENV', MercadoPagoSettings::accessToken());
        $this->assertSame(30, MercadoPagoSettings::pixExpirationMinutes());

        // Salvo no painel → tem prioridade.
        Setting::put('mp_public_key', 'PK-PANEL');
        Setting::put('mp_access_token', 'AT-PANEL');
        Setting::put('mp_pix_expiration', '15');

        $this->assertSame('PK-PANEL', MercadoPagoSettings::publicKey());
        $this->assertSame('AT-PANEL', MercadoPagoSettings::accessToken());
        $this->assertSame(15, MercadoPagoSettings::pixExpirationMinutes());
    }

    public function test_panel_save_then_screen_reflects_value(): void
    {
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')->post('/painel/config', [
            'mp_public_key' => 'APP_USR-PANEL-KEY',
            'mp_access_token' => 'APP_USR-PANEL-TOKEN',
            'mp_pix_expiration' => 20,
        ])->assertRedirect();

        $this->actingAs($organizer, 'painel')
            ->get('/painel/config')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mp.publicKey', 'APP_USR-PANEL-KEY')
                ->where('mp.accessTokenConfigured', true)
                ->where('mp.pixExpiration', 20)
            );
    }
}
