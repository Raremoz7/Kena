<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Quantos e-mails o Kena enviou hoje, para acompanhar a cota grátis do SMTP
 * principal (Brevo: 300/dia). Conta cada envio confirmado (MessageSent).
 * O dia segue o horário de Brasília.
 */
final class MailUsage
{
    public const DEFAULT_DAILY_LIMIT = 300;

    /** A partir desta fração do limite o painel avisa. */
    public const WARN_RATIO = 0.8;

    public static function dailyLimit(): int
    {
        $saved = (int) (Setting::get('mail_daily_limit') ?? 0);

        return $saved > 0 ? $saved : self::DEFAULT_DAILY_LIMIT;
    }

    public static function sentToday(): int
    {
        return (int) Cache::get(self::key(), 0);
    }

    /** Soma um envio e avisa no log ao passar de 80% e de 100% do limite. */
    public static function recordSent(): void
    {
        $key = self::key();
        Cache::add($key, 0, now()->addDays(2));
        $sent = (int) Cache::increment($key);

        $limit = self::dailyLimit();
        if ($sent === (int) ceil($limit * self::WARN_RATIO)) {
            Log::warning("E-mail: {$sent} de {$limit} envios do dia no SMTP principal.");
        } elseif ($sent === $limit) {
            Log::warning("E-mail: limite diário de {$limit} atingido; os próximos envios dependem do SMTP secundário.");
        }
    }

    /** @return array{sentToday: int, dailyLimit: int, warn: bool, over: bool} */
    public static function summary(): array
    {
        $sent = self::sentToday();
        $limit = self::dailyLimit();

        return [
            'sentToday' => $sent,
            'dailyLimit' => $limit,
            'warn' => $sent >= $limit * self::WARN_RATIO,
            'over' => $sent >= $limit,
        ];
    }

    private static function key(): string
    {
        return 'mail-sent:'.Carbon::now('America/Sao_Paulo')->toDateString();
    }
}
