<?php

namespace App\Services;

use App\Exceptions\CpfInUseException;
use App\Exceptions\GuestAccountExistsException;
use App\Models\User;

/**
 * Identidade do comprador convidado (checkout sem senha). Só é chamado depois
 * que o comprador confirmou o e-mail com o código — então ele é dono do
 * endereço. Cria um usuário leve ou reusa o que já existe, sem sobrescrever os
 * dados dele. Conta com senha/login social continua exigindo login.
 */
class GuestIdentityService
{
    /**
     * @param  array{email: string, name: string, cpf: string}  $data
     *
     * @throws GuestAccountExistsException
     * @throws CpfInUseException
     */
    public function identify(array $data): User
    {
        $email = EmailVerificationCodeService::normalize($data['email']);

        $existing = User::where('email', $email)->first();
        if ($existing !== null) {
            if ($existing->password !== null || $existing->google_id !== null) {
                throw new GuestAccountExistsException;
            }

            return $existing;
        }

        // Limite de ingressos é por CPF: outro e-mail com o mesmo CPF não abre conta nova.
        $cpf = (string) preg_replace('/\D/', '', $data['cpf']);
        if (User::where('cpf', $cpf)->exists()) {
            throw new CpfInUseException;
        }

        $user = User::create([
            'email' => $email,
            'name' => $data['name'],
            'cpf' => $cpf,
        ]);

        // email_verified_at não é fillable: grava à parte, já que o código confirmou o e-mail.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
