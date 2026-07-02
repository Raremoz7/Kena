<?php

namespace Tests\Feature\Auth;

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
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('tickets.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'helena@example.com',
            'phone' => '61999998888',
            'cpf' => '52998224725',
        ]);
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
