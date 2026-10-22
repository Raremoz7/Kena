<?php

namespace App\Services;

use App\Mail\EmailVerificationCodeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Código de 6 dígitos que prova que o comprador recebe e-mails no endereço
 * informado (convidado e cadastro com senha). Evita ingresso indo para e-mail
 * digitado errado e impede usar o e-mail de outra pessoa.
 *
 * O código fica só como HMAC no cache; uso único, com prazo e tentativas limitadas.
 */
class EmailVerificationCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const SENDS_PER_WINDOW = 3;

    public const SEND_WINDOW_SECONDS = 600;

    /**
     * Envia um código novo (invalida o anterior). Retorna os segundos de espera
     * quando o limite de envios do e-mail estourou, ou null se enviou.
     */
    public function send(string $email): ?int
    {
        $email = self::normalize($email);
        $limiterKey = 'email-code-send:'.self::digest($email);

        if (RateLimiter::tooManyAttempts($limiterKey, self::SENDS_PER_WINDOW)) {
            return RateLimiter::availableIn($limiterKey);
        }
        RateLimiter::hit($limiterKey, self::SEND_WINDOW_SECONDS);

        Mail::to($email)->send(new EmailVerificationCodeMail($this->issue($email), self::TTL_MINUTES));

        return null;
    }

    /** Gera e guarda um código novo para o e-mail (sem enviar). */
    public function issue(string $email): string
    {
        $email = self::normalize($email);
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::cacheKey($email), [
            'hash' => self::hashCode($email, $code),
            'attempts' => 0,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $code;
    }

    /** Confere e consome o código. Errou demais, o código morre e é preciso pedir outro. */
    public function verify(string $email, string $code): bool
    {
        $email = self::normalize($email);
        $key = self::cacheKey($email);
        $entry = Cache::get($key);

        if (! is_array($entry)) {
            return false;
        }

        if (hash_equals($entry['hash'], self::hashCode($email, trim($code)))) {
            Cache::forget($key);

            return true;
        }

        $attempts = $entry['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
        } else {
            Cache::put($key, ['hash' => $entry['hash'], 'attempts' => $attempts], now()->addMinutes(self::TTL_MINUTES));
        }

        return false;
    }

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private static function digest(string $email): string
    {
        return hash('sha256', $email);
    }

    private static function cacheKey(string $email): string
    {
        return 'email-code:'.self::digest($email);
    }

    private static function hashCode(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }
}
