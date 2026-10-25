<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25',
            'password' => 'password123',
            'code' => app(EmailVerificationCodeService::class)->issue('helena@example.com'),
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('tickets.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'helena@example.com',
            'phone' => '61999998888',
            'cpf' => '52998224725',
        ]);
        $this->assertNotNull(User::where('email', 'helena@example.com')->value('email_verified_at'));
    }

    public function test_registration_requires_the_email_code()
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena3@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'helena3@example.com']);
    }

    public function test_registration_rejects_a_code_issued_for_another_email()
    {
        $code = app(EmailVerificationCodeService::class)->issue('outra@example.com');

        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena4@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25',
            'password' => 'password123',
            'code' => $code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_registration_requires_valid_cpf()
    {
        $response = $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Helena Souza',
            'email' => 'helena2@example.com',
            'phone' => '(61) 99999-8888',
            'cpf' => '111.111.111-11',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertGuest();
    }

    public function test_registration_screen_sends_the_email_code_url()
    {
        // Bug: o endereço do código tinha ido para a tela de redefinir senha.
        $this->get(route('register'))
            ->assertInertia(fn ($page) => $page->where('emailCodeUrl', route('email-code.send')));
    }

    public function test_registering_with_a_google_email_warns_to_use_google()
    {
        User::factory()->create(['email' => 'davi@example.com', 'google_id' => 'g-1', 'password' => null]);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Davi', 'email' => 'DAVI@example.com', 'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25', 'password' => 'nova-senha-123',
            'code' => app(EmailVerificationCodeService::class)->issue('davi@example.com'),
        ])->assertSessionHasErrors(['email' => 'Este e-mail já tem conta pelo Google. Entre com o botão “Continuar com Google”.']);

        $this->assertGuest();
        $this->assertNull(User::where('email', 'davi@example.com')->value('password'));
    }

    public function test_guest_buyer_account_completes_registration_with_the_code_and_logs_in()
    {
        // Conta criada numa compra como convidado: sem senha e sem Google.
        $user = User::factory()->create([
            'name' => 'Mãe da Ana', 'email' => 'mae@example.com', 'password' => null,
            'google_id' => null, 'cpf' => null, 'phone' => null,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Outro Nome', 'email' => 'mae@example.com', 'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25', 'password' => 'nova-senha-123',
            'code' => app(EmailVerificationCodeService::class)->issue('mae@example.com'),
        ])->assertRedirect(route('tickets.index', absolute: false));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('Mãe da Ana', $user->name, 'nome que já existia não muda');
        $this->assertSame('52998224725', $user->cpf, 'o que faltava é preenchido');
        $this->assertTrue(Hash::check('nova-senha-123', $user->password));
        $this->assertSame(1, User::count());
    }

    public function test_guest_buyer_account_without_valid_code_is_not_touched()
    {
        $user = User::factory()->create(['email' => 'mae@example.com', 'google_id' => null, 'password' => null]);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Intruso', 'email' => 'mae@example.com', 'phone' => '(61) 99999-8888',
            'cpf' => '529.982.247-25', 'password' => 'senha-do-intruso1', 'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertNull($user->fresh()->password);
    }

    public function test_google_account_trying_password_login_is_told_to_use_google()
    {
        User::factory()->create(['email' => 'davi@example.com', 'google_id' => 'g-1', 'password' => null]);

        $this->from(route('login'))->post(route('login.store'), ['email' => 'davi@example.com', 'password' => 'qualquer'])
            ->assertSessionHasErrors(['email' => 'Este e-mail entra pelo Google. Use o botão “Continuar com Google” acima.']);

        $this->assertGuest();
    }

    public function test_guest_buyer_account_trying_password_login_is_told_to_create_one()
    {
        User::factory()->create(['email' => 'mae@example.com', 'google_id' => null, 'password' => null]);

        $this->from(route('login'))->post(route('login.store'), ['email' => 'mae@example.com', 'password' => 'qualquer'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_login_still_works()
    {
        $user = User::factory()->create(['email' => 'pai@example.com', 'password' => 'senha-certa-123']);

        $this->post(route('login.store'), ['email' => 'PAI@example.com', 'password' => 'senha-certa-123'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_ends_the_buyer_session()
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect();

        $this->assertGuest();
    }
}
