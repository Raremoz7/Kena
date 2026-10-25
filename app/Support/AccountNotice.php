<?php

namespace App\Support;

use App\Models\User;
use App\Services\EmailVerificationCodeService;

/**
 * Aviso de "esse e-mail já tem conta" com o caminho certo para entrar. O código
 * por e-mail é só para criar conta (ou comprar como convidado) — quem já entra
 * com Google ou senha é mandado para o login, com o botão certo.
 */
final class AccountNotice
{
    /**
     * Conta que já entra sozinha (Google ou senha) para este e-mail, ou null.
     * Conta leve de convidado (sem senha e sem Google) não conta: ela usa o código.
     *
     * @return array{message: string, google: bool, password: bool}|null
     */
    public static function forEmail(string $email): ?array
    {
        $user = User::where('email', EmailVerificationCodeService::normalize($email))->first();

        return $user !== null ? self::forUser($user) : null;
    }

    /** @return array{message: string, google: bool, password: bool}|null */
    public static function forUser(User $user): ?array
    {
        $google = $user->google_id !== null;
        $password = $user->password !== null;

        if (! $google && ! $password) {
            return null;
        }

        return [
            'message' => match (true) {
                $google && ! $password => 'Este e-mail já tem conta pelo Google. Entre com o botão “Continuar com Google”.',
                $google => 'Este e-mail já tem conta. Entre com o Google ou com a sua senha.',
                default => 'Este e-mail já tem conta. Entre com a sua senha (ou use “Esqueci minha senha”).',
            },
            'google' => $google,
            'password' => $password,
        ];
    }
}
