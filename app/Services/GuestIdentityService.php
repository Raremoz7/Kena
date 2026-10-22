<?php

namespace App\Services;

use App\Exceptions\GuestAccountExistsException;
use App\Exceptions\GuestAccountNeedsVerificationException;
use App\Models\User;

/**
 * Identidade do comprador convidado (checkout sem senha). Cria um usuário leve
 * por e-mail. Se o e-mail já tem conta, nunca loga direto nela — digitar o
 * e-mail de outra pessoa não pode dar acesso aos ingressos dela:
 * conta com senha/login social exige login; conta leve exige o magic-link.
 */
class GuestIdentityService
{
    /**
     * @param  array{email: string, name: string, cpf: string}  $data
     *
     * @throws GuestAccountExistsException
     * @throws GuestAccountNeedsVerificationException
     */
    public function identify(array $data): User
    {
        $email = mb_strtolower(trim($data['email']));
        $cpf = (string) preg_replace('/\D/', '', $data['cpf']);

        $existing = User::where('email', $email)->first();
        if ($existing !== null) {
            if ($existing->password !== null || $existing->google_id !== null) {
                throw new GuestAccountExistsException;
            }

            throw new GuestAccountNeedsVerificationException($existing);
        }

        return User::create([
            'email' => $email,
            'name' => $data['name'],
            'cpf' => $cpf,
            'email_verified_at' => now(),
        ]);
    }
}
