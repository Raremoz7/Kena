<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração de e-mail (SMTP) editável pelo painel. O que foi salvo no Setting
 * tem prioridade; cai para config/.env. `apply()` injeta em runtime para o mailer.
 */
final class MailSettings
{
    /** Aplica as configurações salvas no painel ao config do mailer (se houver). */
    public static function apply(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $map = Setting::map();
        $host = $map['mail_host'] ?? null;
        if (blank($host)) {
            return; // nada configurado no painel → usa o .env
        }

        $encryption = $map['mail_encryption'] ?? null;

        config([
            'mail.default' => $map['mail_mailer'] ?? 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) ($map['mail_port'] ?? 587),
            'mail.mailers.smtp.username' => $map['mail_username'] ?? null,
            'mail.mailers.smtp.password' => $map['mail_password'] ?? null,
            'mail.mailers.smtp.encryption' => $encryption ?: null,
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : null,
            'mail.from.address' => $map['mail_from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $map['mail_from_name'] ?? config('mail.from.name'),
        ]);
    }

    /** @return array<string, mixed> Campos não-secretos para exibir no painel. */
    public static function display(): array
    {
        $get = fn (string $key, mixed $default = null): mixed => Setting::get($key) ?? $default;

        return [
            'mailer' => (string) $get('mail_mailer', config('mail.default')),
            'host' => (string) $get('mail_host', config('mail.mailers.smtp.host')),
            'port' => (int) $get('mail_port', (int) config('mail.mailers.smtp.port', 587)),
            'username' => (string) $get('mail_username', ''),
            'encryption' => (string) $get('mail_encryption', 'tls'),
            'fromAddress' => (string) $get('mail_from_address', config('mail.from.address')),
            'fromName' => (string) $get('mail_from_name', config('mail.from.name')),
            'passwordConfigured' => filled(Setting::get('mail_password')),
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
