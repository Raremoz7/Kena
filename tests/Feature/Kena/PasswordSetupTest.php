<?php

namespace Tests\Feature\Kena;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSetupTest extends TestCase
{
    use RefreshDatabase;

    /** Conta leve, criada sem a chave password (como no guest checkout/transferência). */
    private function passwordlessUser(): User
    {
        return User::create([
            'email' => 'leve'.uniqid().'@guest.test',
            'name' => 'Convidado',

            'email_verified_at' => now(),
        ]);
    }

    public function test_lightweight_user_can_set_password(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)->get(route('password.setup'))->assertOk();

        $this->actingAs($user)
            ->post(route('password.setup.store'), [
                'password' => 'segredo123',
                'password_confirmation' => 'segredo123',
            ])
            ->assertRedirect(route('tickets.index'));

        $fresh = $user->refresh();
        $this->assertNotNull($fresh->password);
        $this->assertTrue(Hash::check('segredo123', $fresh->password));
    }

    public function test_user_with_password_is_redirected_from_setup(): void
    {
        $user = User::factory()->create(); // factory já define senha

        $this->actingAs($user)->get(route('password.setup'))->assertRedirect(route('tickets.index'));
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = $this->passwordlessUser();

        $this->actingAs($user)
            ->post(route('password.setup.store'), [
                'password' => 'segredo123',
                'password_confirmation' => 'diferente',
            ])
            ->assertSessionHasErrors('password');
    }
}
