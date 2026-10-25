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

    /** Validade curta do link enviado no meio da compra (conta leve já existente). */
    public const CHECKOUT_TTL_MINUTES = 30;

    /**
     * Link para retomar a compra: loga e volta para `$redirect` (caminho local).
     * O redirect entra na assinatura, então não pode ser trocado por terceiros.
     */
    public static function forCheckout(User $user, string $redirect): string
    {
        $token = Str::random(48);
        $user->forceFill(['magic_login_token' => hash('sha256', $token)])->save();

        return URL::temporarySignedRoute(
            'magic-login',
            now()->addMinutes(self::CHECKOUT_TTL_MINUTES),
            ['user' => $user->id, 'token' => $token, 'redirect' => $redirect],
        );
    }

    /** Só caminhos locais ("/x"), nunca "//host" ou URL absoluta. */
    public static function safeRedirect(?string $path): ?string
    {
        if ($path === null || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return $path;
    }

    /** Confere o token sem gastar (a página do botão "Entrar"). */
    public static function isValid(User $user, string $token): bool
    {
        return $token !== ''
            && $user->magic_login_token !== null
            && hash_equals($user->magic_login_token, hash('sha256', $token));
    }

    /** Consome o token (uso único). Retorna se o par usuário/token era válido. */
    public static function consume(User $user, string $token): bool
    {
        if (! self::isValid($user, $token)) {
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
