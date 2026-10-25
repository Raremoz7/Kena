<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketsIssuedMail;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\SampleTicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class SettingsPanelTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_saving_mail_settings_persists_and_applies_to_mailer(): void
    {
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')->post('/painel/config', [
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
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')
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
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('admin.settings'))->assertRedirect(route('painel.login'));
    }

    public function test_organizer_can_send_test_email(): void
    {
        Mail::fake();
        $organizer = PanelUser::factory()->create(['email' => 'op@test.com']);

        $this->actingAs($organizer, 'painel')
            ->postJson(route('admin.settings.test-mail'))
            ->assertOk()
            ->assertJson(['message' => 'E-mail de teste enviado para op@test.com.']);

        // É o e-mail real de ingressos (modo teste), enviado na hora, sem gravar nada.
        Mail::assertSent(TicketsIssuedMail::class, fn (TicketsIssuedMail $m): bool => $m->preview && $m->hasTo('op@test.com'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Ticket::count());
    }

    public function test_test_email_renders_like_the_real_ticket_email(): void
    {
        $session = $this->makeSession(1);
        $session->load('event.venue');

        $mail = new TicketsIssuedMail(SampleTicketOrder::make('Davi Teste'), preview: true);
        $html = $mail->render();

        $this->assertSame('[Teste] Seus ingressos — '.$session->event->title, $mail->envelope()->subject);
        $this->assertStringContainsString('Pagamento aprovado', $html);
        $this->assertStringContainsString($session->event->title, $html);
        $this->assertStringContainsString('TESTE-0001', $html);
        $this->assertStringContainsString('TESTE-0002', $html);
        $this->assertStringContainsString('Davi Teste', $html);
        $this->assertStringContainsString(route('tickets.index'), $html);
        $this->assertSame(0, Order::count());
    }

    public function test_test_email_works_without_any_event(): void
    {
        $html = (new TicketsIssuedMail(SampleTicketOrder::make('Davi Teste'), preview: true))->render();

        $this->assertStringContainsString('Espetáculo de exemplo', $html);
        $this->assertStringContainsString('TESTE-0001', $html);
    }

    public function test_buyer_cannot_send_test_email(): void
    {
        $buyer = User::factory()->create();

        // Chamada JSON sem login do painel: 401 (visita normal redireciona ao login).
        $this->actingAs($buyer)->postJson(route('admin.settings.test-mail'))->assertUnauthorized();
        $this->actingAs($buyer)->post(route('admin.settings.test-mail'))->assertRedirect(route('painel.login'));
    }
}
