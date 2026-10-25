<?php

namespace Tests\Feature\Kena;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Avisos do Laravel/Fortify em português (antes saíam em inglês). */
class PortugueseMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('pt_BR');
    }

    public function test_wrong_password_message_is_in_portuguese(): void
    {
        User::factory()->create(['email' => 'pai@escola.test']);

        $this->from(route('login'))->post(route('login.store'), ['email' => 'pai@escola.test', 'password' => 'errada123'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
    }

    public function test_password_reset_email_is_in_portuguese(): void
    {
        $mail = (new ResetPassword('token'))->toMail(User::factory()->make());

        $this->assertSame('Redefina sua senha', $mail->subject);
        $this->assertSame('Redefinir senha', $mail->actionText);
        $this->assertStringContainsString('Olá!', (string) $mail->render());
    }

    public function test_invalid_reset_link_message_is_in_portuguese(): void
    {
        $user = User::factory()->create();

        $this->from('/reset-password/x')->post(route('password.update'), [
            'token' => 'invalido', 'email' => $user->email,
            'password' => 'nova-senha1', 'password_confirmation' => 'nova-senha1',
        ])->assertSessionHasErrors(['email' => 'Este link de redefinição é inválido ou já expirou. Peça um novo.']);
    }

    public function test_required_field_message_is_in_portuguese(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Preencha o e-mail.']);
    }
}
