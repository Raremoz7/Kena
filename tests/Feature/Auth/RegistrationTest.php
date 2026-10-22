<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'password' => 'password',
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
            'password' => 'password',
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
            'password' => 'password',
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
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('cpf');
        $this->assertGuest();
    }
}
