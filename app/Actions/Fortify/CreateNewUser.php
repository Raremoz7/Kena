<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly EmailVerificationCodeService $codes) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
            'cpf' => $this->cpfRules(),
            'password' => ['required', 'string', Password::default()],
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Digite o código que enviamos para o seu e-mail.',
            'code.digits' => 'O código tem 6 números.',
        ])->validate();

        // A conta só nasce com o e-mail confirmado: evita endereço digitado errado.
        if (! $this->codes->verify($input['email'], $input['code'])) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido ou expirado. Confira o e-mail ou peça um novo código.',
            ]);
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => preg_replace('/\D/', '', $input['phone']),
            'cpf' => preg_replace('/\D/', '', $input['cpf']),
            'password' => $input['password'],
        ]);

        // email_verified_at não é fillable: grava à parte, já que o código confirmou o e-mail.
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
