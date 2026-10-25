<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Confere se o domínio de um e-mail existe e recebe e-mail (MX, ou A como
 * reserva) e sugere a correção de erros comuns de digitação ("gmial.com").
 * Falha do DNS não bloqueia ninguém: só recusa quando o domínio comprovadamente
 * não tem registro nenhum.
 */
class EmailDomain
{
    /** Domínios mais usados pelos pais (base das sugestões). */
    public const COMMON = [
        'gmail.com', 'hotmail.com', 'outlook.com', 'live.com', 'msn.com', 'icloud.com',
        'yahoo.com', 'yahoo.com.br', 'hotmail.com.br', 'outlook.com.br', 'bol.com.br',
        'uol.com.br', 'terra.com.br', 'globo.com', 'ig.com.br',
    ];

    /** @var (callable(string): bool)|null */
    private static $resolver = null;

    /** Testes: troca a consulta de DNS (null volta ao DNS de verdade). */
    public static function resolveUsing(?callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function domainOf(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '' : strtolower(trim(substr($email, $at + 1)));
    }

    public static function receivesMail(string $email): bool
    {
        $domain = self::domainOf($email);
        if ($domain === '' || in_array($domain, self::COMMON, true)) {
            return $domain !== '';
        }

        if (self::$resolver !== null) {
            return (self::$resolver)($domain);
        }

        return Cache::remember('email-domain:'.$domain, now()->addDay(), function () use ($domain): bool {
            $mx = @dns_get_record($domain.'.', DNS_MX);
            if ($mx !== false && $mx !== []) {
                return true;
            }
            $a = @dns_get_record($domain.'.', DNS_A);

            // false = DNS falhou (não dá para afirmar nada): deixa passar.
            return $mx === false || $a === false || $a !== [];
        });
    }

    /** "ana@gmial.com" → "ana@gmail.com"; null quando não parece erro de digitação. */
    public static function suggestion(string $email): ?string
    {
        $email = trim($email);
        $domain = self::domainOf($email);
        if ($domain === '' || in_array($domain, self::COMMON, true)) {
            return null;
        }

        $best = null;
        $bestDistance = 3;
        foreach (self::COMMON as $candidate) {
            $distance = levenshtein($domain, $candidate);
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best === null ? null : substr($email, 0, (int) strrpos($email, '@') + 1).$best;
    }
}
