<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(string $id, string $email, string $name, bool $emailVerified = true): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($id);
        $abstract->shouldReceive('getEmail')->andReturn($email);
        $abstract->shouldReceive('getName')->andReturn($name);
        $abstract->shouldReceive('getRaw')->andReturn(['email_verified' => $emailVerified]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstract);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_callback_creates_new_account(): void
    {
        $this->fakeGoogleUser('g-123', 'novo@gmail.com', 'Novo Usuário');

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('tickets.index', absolute: false));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'novo@gmail.com', 'google_id' => 'g-123']);
        $this->assertNotNull(User::where('email', 'novo@gmail.com')->value('email_verified_at'));
    }

    public function test_callback_links_verified_passwordless_account(): void
    {
        // Conta leve de convidado: nasceu depois do código por e-mail.
        $user = User::factory()->create(['email' => 'existente@gmail.com', 'password' => null]);
        $this->fakeGoogleUser('g-456', 'existente@gmail.com', 'Existente');

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-456', $user->fresh()->google_id);
    }

    public function test_callback_does_not_link_account_with_password(): void
    {
        // Quem criou essa conta pode não ser o dono do e-mail: não entrega a sessão.
        $user = User::factory()->create(['email' => 'existente@gmail.com']);
        $this->fakeGoogleUser('g-456', 'existente@gmail.com', 'Existente');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_callback_does_not_link_account_with_unverified_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'existente@gmail.com', 'password' => null]);
        $this->fakeGoogleUser('g-456', 'existente@gmail.com', 'Existente');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_callback_refuses_google_email_not_verified(): void
    {
        $this->fakeGoogleUser('g-789', 'nao-verificado@gmail.com', 'X', emailVerified: false);

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nao-verificado@gmail.com']);
    }

    public function test_callback_logs_in_by_google_id_even_after_email_change(): void
    {
        $user = User::factory()->create(['email' => 'outro@gmail.com', 'google_id' => 'g-111']);
        $this->fakeGoogleUser('g-111', 'antigo@gmail.com', 'X');

        $this->get(route('auth.google.callback'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_redirect_route_responds(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('auth.google.redirect'))->assertRedirect();
    }
}
