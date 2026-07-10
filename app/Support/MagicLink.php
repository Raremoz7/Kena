<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Magic-link de acesso sem senha (contas leves de convidado/transferência).
 *
 * Segurança: além da assinatura da URL, o link carrega um token de USO ÚNICO
 * cujo hash fica no usuário — clicar consome o token, e cada novo e-mail
 * rotaciona o anterior. Um e-mail encaminhado/vazado não vira acesso perpétuo.
 */
final class MagicLink
{
    /** Gera o link e rotaciona o token vigente do usuário. */
    public static function generate(User $user, CarbonInterface $sessionStart): string
    {
        $token = Str::random(48);
        $user->forceFill(['magic_login_token' => hash('sha256', $token)])->save();

        return URL::temporarySignedRoute(
            'magic-login',
            self::expiryFor($sessionStart),
            ['user' => $user->id, 'token' => $token],
        );
    }

    /** Consome o token (uso único). Retorna se o par usuário/token era válido. */
    public static function consume(User $user, string $token): bool
    {
        if ($token === '' || $user->magic_login_token === null) {
            return false;
        }

        if (! hash_equals($user->magic_login_token, hash('sha256', $token))) {
            return false;
        }

        $user->forceFill(['magic_login_token' => null])->save();

        return true;
    }

    /**
     * Validade: até 1 dia após a sessão, com teto de 30 dias e piso de 2 dias
     * (o lembrete D-1 sempre re-envia um link fresco).
     */
    public static function expiryFor(CarbonInterface $sessionStart): CarbonInterface
    {
        $expiry = $sessionStart->copy()->addDay();
        $cap = now()->addDays(30);
        $floor = now()->addDays(2);

        if ($expiry->greaterThan($cap)) {
            return $cap;
        }

        return $expiry->lessThan($floor) ? $floor : $expiry;
    }
}
