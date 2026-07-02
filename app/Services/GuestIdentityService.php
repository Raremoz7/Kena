<?php

namespace App\Services;

use App\Exceptions\GuestAccountExistsException;
use App\Models\User;

/**
 * Identidade do comprador convidado (checkout sem senha). Cria/reusa um usuário
 * leve por e-mail. Se o e-mail já tem conta com senha/login social, exige login
 * (evita sequestro de conta alheia).
 */
class GuestIdentityService
{
    /**
     * @param  array{email: string, name: string, cpf: string}  $data
     *
     * @throws GuestAccountExistsException
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
            $existing->update(['name' => $data['name'], 'cpf' => $cpf]);

            return $existing;
        }

        return User::create([
            'email' => $email,
            'name' => $data['name'],
            'cpf' => $cpf,
            'role' => User::ROLE_BUYER,
            'email_verified_at' => now(),
        ]);
    }
}
