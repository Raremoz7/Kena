<?php

namespace Tests\Feature\Kena;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * CPF digitado com máscara (529.982.247-25) que já está em outra conta: tem que
 * voltar com aviso no campo, não estourar erro 500 no insert.
 */
class RegistrationDuplicateCpfTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email, string $cpf): TestResponse
    {
        return $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Elisa Santos',
            'email' => $email,
            'phone' => '(61) 98171-2766',
            'cpf' => $cpf,
            'password' => 'password123',
            'code' => app(EmailVerificationCodeService::class)->issue($email),
        ]);
    }

    public function test_masked_cpf_already_in_use_returns_a_field_error(): void
    {
        User::factory()->create(['email' => 'primeira@example.com', 'cpf' => '52998224725']);

        $this->register('segunda@example.com', '529.982.247-25')
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors(['cpf' => 'Esse CPF já está em outra conta. Entre com o e-mail dela para continuar.']);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'segunda@example.com']);
        $this->assertSame(1, User::where('cpf', '52998224725')->count());
    }

    public function test_guest_account_cannot_take_a_cpf_from_another_account(): void
    {
        User::factory()->create(['email' => 'dona@example.com', 'cpf' => '52998224725']);
        // Conta de convidado (sem senha, sem CPF) que vai completar o cadastro.
        $guest = User::factory()->create(['email' => 'convidada@example.com', 'cpf' => null, 'password' => null]);

        $this->register('convidada@example.com', '529.982.247-25')
            ->assertSessionHasErrors('cpf');

        $this->assertNull($guest->fresh()->cpf);
    }

    public function test_masked_cpf_still_registers_when_free(): void
    {
        $this->register('nova@example.com', '529.982.247-25')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'nova@example.com', 'cpf' => '52998224725']);
    }
}
