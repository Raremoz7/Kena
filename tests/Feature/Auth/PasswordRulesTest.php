<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/** Senha no padrão comum: 8 caracteres com letra e número, sem símbolo nem maiúscula. */
class PasswordRulesTest extends TestCase
{
    private function errors(string $password): array
    {
        app()->setLocale('pt_BR');

        return Validator::make(['password' => $password], ['password' => [Password::default()]])->errors()->all();
    }

    public function test_simple_password_with_letters_and_numbers_is_accepted(): void
    {
        $this->assertSame([], $this->errors('bailarina7'));
        $this->assertSame([], $this->errors('kena2026'));
    }

    public function test_short_or_one_kind_password_gets_a_clear_message_in_portuguese(): void
    {
        $this->assertContains('A senha deve ter pelo menos 8 caracteres.', $this->errors('abc12'));
        $this->assertSame(['A senha precisa ter pelo menos um número.'], $this->errors('bailarina'));
        $this->assertSame(['A senha precisa ter pelo menos uma letra.'], $this->errors('12345678'));
    }
}
