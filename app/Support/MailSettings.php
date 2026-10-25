<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Configuração de e-mail (SMTP) editável pelo painel. O que foi salvo no Setting
 * tem prioridade; cai para config/.env. `apply()` injeta em runtime para o mailer.
 */
final class MailSettings
{
    /** Aplica as configurações salvas no painel ao config do mailer (se houver). */
    public static function apply(): void
    {
        // Sem banco/tabela cai para o .env.
        $map = Setting::forBoot();
        if ($map === null) {
            return;
        }

        self::applyPrimary($map);
        self::applyBackup($map);
    }

    /**
     * SMTP principal salvo no painel (sem servidor no painel, fica o do .env).
     *
     * @param  array<string, string|null>  $map
     */
    private static function applyPrimary(array $map): void
    {
        $host = $map['mail_host'] ?? null;
        if (blank($host)) {
            return; // nada configurado no painel → usa o .env
        }

        // Campo em branco no painel não apaga o que está no .env: salvar a tela
        // de configurações sem redigitar usuário/senha derrubava o envio de e-mail.
        $pick = fn (string $key, mixed $fallback): mixed => filled($map[$key] ?? null) ? $map[$key] : $fallback;
        $encryption = $pick('mail_encryption', null);

        config([
            'mail.default' => $pick('mail_mailer', config('mail.default', 'smtp')),
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) $pick('mail_port', config('mail.mailers.smtp.port', 587)),
            'mail.mailers.smtp.username' => $pick('mail_username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => $pick('mail_password', config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.encryption' => $encryption ?: null,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : config('mail.mailers.smtp.scheme'),
            'mail.from.address' => $pick('mail_from_address', config('mail.from.address')),
            'mail.from.name' => $pick('mail_from_name', config('mail.from.name')),
        ]);
    }

    /**
     * SMTP secundário: se o principal recusar (ex.: acabou a cota grátis do dia
     * na Brevo), o e-mail sai por ele na hora — o mailer "failover" do Laravel.
     * Vale também com o principal vindo do .env.
     *
     * @param  array<string, string|null>  $map
     */
    private static function applyBackup(array $map): void
    {
        $host = $map['mail_backup_host'] ?? null;
        if (blank($host) || config('mail.default') !== 'smtp') {
            return;
        }

        $encryption = $map['mail_backup_encryption'] ?? 'tls';

        config([
            'mail.mailers.smtp_backup' => [
                'transport' => 'smtp',
                'host' => $host,
                'port' => (int) ($map['mail_backup_port'] ?? 587),
                'username' => $map['mail_backup_username'] ?? null,
                'password' => $map['mail_backup_password'] ?? null,
                'encryption' => $encryption === 'none' ? null : $encryption,
                'scheme' => $encryption === 'ssl' ? 'smtps' : null,
                'timeout' => 15,
            ],
            'mail.mailers.failover' => [
                'transport' => 'failover',
                'mailers' => ['smtp', 'smtp_backup'],
                'retry_after' => 60,
            ],
            'mail.default' => 'failover',
        ]);
    }

    /** @return array<string, mixed> Campos não-secretos do SMTP secundário para o painel. */
    public static function backupDisplay(): array
    {
        $host = (string) (Setting::get('mail_backup_host') ?? '');

        return [
            'configured' => $host !== '',
            'host' => $host,
            'port' => (int) (Setting::get('mail_backup_port') ?? 587),
            'username' => (string) (Setting::get('mail_backup_username') ?? ''),
            'encryption' => (string) (Setting::get('mail_backup_encryption') ?? 'tls'),
            'passwordConfigured' => filled(Setting::get('mail_backup_password')),
        ];
    }

    /** @return array<string, mixed> Campos não-secretos para exibir no painel. */
    public static function display(): array
    {
        $get = fn (string $key, mixed $default = null): mixed => Setting::get($key) ?? $default;

        return [
            'mailer' => (string) $get('mail_mailer', config('mail.default')),
            'host' => (string) $get('mail_host', config('mail.mailers.smtp.host')),
            'port' => (int) $get('mail_port', (int) config('mail.mailers.smtp.port', 587)),
            'username' => (string) $get('mail_username', config('mail.mailers.smtp.username') ?? ''),
            'encryption' => (string) $get('mail_encryption', 'tls'),
            'fromAddress' => (string) $get('mail_from_address', config('mail.from.address')),
            'fromName' => (string) $get('mail_from_name', config('mail.from.name')),
            // Senha no painel ou no .env: os dois valem (o painel só sobrepõe se preenchido).
            'passwordConfigured' => filled(Setting::get('mail_password')) || filled(config('mail.mailers.smtp.password')),
        ];
    }

    /** E-mail está minimamente configurado para enviar? (ignora os placeholders do .env) */
    public static function isConfigured(): bool
    {
        $host = Setting::get('mail_host');
        if (blank($host)) {
            $envHost = (string) config('mail.mailers.smtp.host');
            $host = ($envHost !== '' && $envHost !== '127.0.0.1') ? $envHost : null;
        }

        $from = Setting::get('mail_from_address') ?? (string) config('mail.from.address');

        return filled($host) && filled($from) && $from !== 'hello@example.com';
    }
}
