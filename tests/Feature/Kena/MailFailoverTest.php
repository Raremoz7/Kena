<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
use App\Models\Setting;
use App\Support\MailSettings;
use App\Support\MailUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** SMTP secundário (failover) e contador diário de e-mails. */
class MailFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_smtp_turns_on_failover_after_the_primary(): void
    {
        config(['mail.default' => 'smtp']);
        Setting::put('mail_backup_host', 'mail.smtp2go.com');
        Setting::put('mail_backup_port', '2525');
        Setting::put('mail_backup_username', 'kena');
        Setting::put('mail_backup_password', 'segredo');

        MailSettings::apply();

        $this->assertSame('failover', config('mail.default'));
        $this->assertSame(['smtp', 'smtp_backup'], config('mail.mailers.failover.mailers'));
        $this->assertSame('mail.smtp2go.com', config('mail.mailers.smtp_backup.host'));
        $this->assertSame(2525, config('mail.mailers.smtp_backup.port'));
        $this->assertSame('segredo', config('mail.mailers.smtp_backup.password'));
    }

    public function test_without_backup_the_primary_stays_alone(): void
    {
        config(['mail.default' => 'smtp']);

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_message_falls_back_when_the_primary_refuses(): void
    {
        // Principal num endereço que recusa conexão; o reserva (array) recebe.
        config([
            'mail.default' => 'failover',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
            'mail.mailers.failover.mailers' => ['smtp', 'array'],
        ]);

        $sent = Mail::raw('Seu código é 123456', fn ($m) => $m->to('pai@escola.test')->subject('Código'));

        // O principal recusou e mesmo assim o e-mail saiu (pelo reserva), sem exceção.
        $this->assertNotNull($sent);
        $this->assertSame(1, MailUsage::sentToday());
    }

    public function test_panel_saves_and_clears_the_backup(): void
    {
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')->post('/painel/config', [
            'mail_backup_host' => 'mail.smtp2go.com',
            'mail_backup_port' => 2525,
            'mail_backup_username' => 'kena',
            'mail_backup_password' => 'segredo',
            'mail_backup_encryption' => 'tls',
            'mail_daily_limit' => 300,
        ])->assertRedirect();

        $this->assertSame('mail.smtp2go.com', Setting::get('mail_backup_host'));
        $this->assertSame('segredo', Setting::get('mail_backup_password'));
        $this->assertTrue(MailSettings::backupDisplay()['passwordConfigured']);

        // Salvar de novo sem redigitar a senha mantém a senha.
        $this->actingAs($organizer, 'painel')->post('/painel/config', ['mail_backup_host' => 'mail.smtp2go.com'])->assertRedirect();
        $this->assertSame('segredo', Setting::get('mail_backup_password'));

        // Servidor em branco desliga o secundário e apaga a senha.
        $this->actingAs($organizer, 'painel')->post('/painel/config', ['mail_backup_host' => ''])->assertRedirect();
        $this->assertFalse(MailSettings::backupDisplay()['configured']);
        $this->assertNull(Setting::get('mail_backup_password'));
    }

    public function test_daily_counter_counts_sent_messages_and_flags_the_limit(): void
    {
        config(['mail.default' => 'array']);
        Setting::put('mail_daily_limit', '5');

        foreach (range(1, 4) as $i) {
            Mail::raw("Mensagem {$i}", fn ($m) => $m->to("p{$i}@escola.test")->subject('Teste'));
        }

        $summary = MailUsage::summary();
        $this->assertSame(4, $summary['sentToday']);
        $this->assertSame(5, $summary['dailyLimit']);
        $this->assertTrue($summary['warn']); // 4 ≥ 80% de 5
        $this->assertFalse($summary['over']);

        Mail::raw('Mensagem 5', fn ($m) => $m->to('p5@escola.test')->subject('Teste'));
        $this->assertTrue(MailUsage::summary()['over']);
    }

    public function test_default_limit_is_the_brevo_free_quota(): void
    {
        $this->assertSame(300, MailUsage::dailyLimit());
    }
}
