<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Support\AccountNotice;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly EmailVerificationCodeService $codes) {}

    /**
     * Cadastro com senha, confirmado pelo código enviado ao e-mail. Quem já tem
     * conta com Google ou senha recebe o aviso para entrar. Quem comprou antes
     * como convidado (conta sem senha) completa o cadastro nessa conta e entra.
     * Fortify faz o login com o usuário devolvido.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $existing = isset($input['email'])
            ? User::where('email', EmailVerificationCodeService::normalize((string) $input['email']))->first()
            : null;

        // Conta que já entra com Google ou senha: aviso claro para entrar, sem cadastro novo.
        if ($existing !== null && ($notice = AccountNotice::forUser($existing)) !== null) {
            throw ValidationException::withMessages(['email' => $notice['message']]);
        }

        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($existing?->id),
            'phone' => $this->phoneRules(),
            'cpf' => $this->cpfRules($existing?->id),
            'password' => ['required', 'string', Password::default()],
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Digite o código que enviamos para o seu e-mail.',
            'code.digits' => 'O código tem 6 números.',
        ])->validate();

        // A conta só nasce (ou é completada) com o e-mail confirmado.
        if (! $this->codes->verify($input['email'], $input['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido ou expirado. Confira o e-mail ou peça um novo código.',
            ]);
        }

        if ($existing !== null) {
            return $this->completeExisting($existing, $input);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => EmailVerificationCodeService::normalize($input['email']),
            'phone' => preg_replace('/\D/', '', $input['phone']),
            'cpf' => preg_replace('/\D/', '', $input['cpf']),
            'password' => $input['password'],
        ]);

        // email_verified_at não é fillable: grava à parte, já que o código confirmou o e-mail.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Conta de convidado (sem senha e sem Google): grava a senha e preenche o
     * que faltava, sem trocar o que a pessoa já tinha.
     *
     * @param  array<string, string>  $input
     */
    private function completeExisting(User $user, array $input): User
    {
        $user->forceFill([
            'name' => filled($user->name) ? $user->name : $input['name'],
            'phone' => filled($user->phone) ? $user->phone : preg_replace('/\D/', '', $input['phone']),
            'cpf' => filled($user->cpf) ? $user->cpf : preg_replace('/\D/', '', $input['cpf']),
            'password' => $input['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }
}
