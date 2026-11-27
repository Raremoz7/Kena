<?php

namespace Tests\Feature\Kena;

use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Configuração da organização x configuração da plataforma. */
class MultiTenantSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_does_not_receive_platform_settings(): void
    {
        Setting::put('mail_host', 'smtp.plataforma.test');
        $organizer = PanelUser::factory()->forOrganization()->create();

        $this->actingAs($organizer, 'painel')->get('/painel/config')
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'organization')
                ->where('mail.host', '')
                ->where('testMailUrl', ''));
    }

    public function test_organizer_saves_own_descriptor_but_not_platform_settings(): void
    {
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $organizer = PanelUser::factory()->forOrganization($b)->create();

        $this->actingAs($organizer, 'painel')->post('/painel/config', [
            'organization_name' => 'Teatro B Produções',
            'mp_statement_descriptor' => 'TEATRO B',
            'mp_pix_expiration' => 15,
            'mail_host' => 'smtp.invasor.test',
            'mp_webhook_secret' => 'invasor',
            'mp_access_token' => 'APP_USR-INVASOR',
        ])->assertRedirect();

        $this->assertSame('Teatro B Produções', $b->fresh()?->name);
        $this->assertSame('TEATRO B', $b->fresh()?->statement_descriptor);
        $this->assertSame(15, $b->fresh()?->pix_expiration_minutes);
        $this->assertSame('TEATRO B', MercadoPagoSettings::statementDescriptor($b->id));
        $this->assertNull(Setting::get('mail_host'));
        $this->assertNull(Setting::get('mp_webhook_secret'));
        $this->assertNull(Setting::get('mp_access_token'));

        // Outra organização continua com o padrão.
        $this->assertSame('KENA INGRESSOS', MercadoPagoSettings::statementDescriptor(Organization::DEFAULT_ID));
    }

    public function test_organizer_cannot_reach_platform_settings(): void
    {
        $as = $this->actingAs(PanelUser::factory()->create(), 'painel');

        $as->get('/admin/config')->assertRedirect('/admin/login');
        $as->post('/admin/config', ['mail_host' => 'x'])->assertRedirect('/admin/login');
        $as->postJson('/admin/config/testar-email')->assertUnauthorized();
        $this->assertNull(Setting::get('mail_host'));
    }

    public function test_super_admin_manages_global_settings(): void
    {
        $this->actingAs(PlatformAdmin::factory()->create(), 'admin')->post('/admin/config', [
            'mail_host' => 'smtp.plataforma.test',
            'mp_statement_descriptor' => 'KENA PADRAO',
        ])->assertRedirect();

        $this->assertSame('smtp.plataforma.test', Setting::get('mail_host'));
        // Padrão da plataforma: vale para quem não definiu o próprio.
        $this->assertSame('KENA PADRAO', MercadoPagoSettings::statementDescriptor(Organization::DEFAULT_ID));
    }
}
