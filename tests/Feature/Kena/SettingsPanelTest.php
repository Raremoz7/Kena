<?php

namespace Tests\Feature\Kena;

use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_mail_settings_persists_and_applies_to_mailer(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $this->actingAs($organizer)->post('/dashboard/config', [
            'mail_host' => 'smtp.test.com',
            'mail_port' => 587,
            'mail_username' => 'u@test.com',
            'mail_password' => 'secret-pass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'from@test.com',
            'mail_from_name' => 'Kena',
        ])->assertRedirect();

        $this->assertSame('smtp.test.com', Setting::get('mail_host'));
        $this->assertSame('secret-pass', Setting::get('mail_password'));

        MailSettings::apply();
        $this->assertSame('smtp.test.com', config('mail.mailers.smtp.host'));
        $this->assertSame('from@test.com', config('mail.from.address'));
        $this->assertTrue(MailSettings::isConfigured());
    }

    public function test_settings_page_exposes_checklist_and_webhook_url(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $this->actingAs($organizer)
            ->get(route('admin.settings'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/settings')
                ->has('setup.mpAccessToken')
                ->has('setup.mail')
                ->has('mail.host')
                ->where('webhookUrl', route('webhooks.mercadopago'))
            );
    }

    public function test_buyer_cannot_open_settings(): void
    {
        $buyer = User::factory()->create(['role' => User::ROLE_BUYER]);

        $this->actingAs($buyer)->get(route('admin.settings'))->assertForbidden();
    }

    public function test_organizer_can_send_test_email(): void
    {
        Mail::fake();
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER, 'email' => 'op@test.com']);

        $this->actingAs($organizer)
            ->postJson(route('admin.settings.test-mail'))
            ->assertOk()
            ->assertJson(['message' => 'E-mail de teste enviado para op@test.com.']);
    }

    public function test_buyer_cannot_send_test_email(): void
    {
        $buyer = User::factory()->create(['role' => User::ROLE_BUYER]);

        $this->actingAs($buyer)->postJson(route('admin.settings.test-mail'))->assertForbidden();
    }
}
