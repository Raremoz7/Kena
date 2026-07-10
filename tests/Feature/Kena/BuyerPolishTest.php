<?php

namespace Tests\Feature\Kena;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Polimentos do fluxo do comprador. */
class BuyerPolishTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_passwordless_account_is_redirected_to_password_setup_on_security(): void
    {
        // Conta leve: criada sem a chave password (guest checkout / transferência).
        $guest = User::forceCreate([
            'name' => 'Convidado',
            'email' => 'guest@example.com',
            'role' => User::ROLE_BUYER,
            'email_verified_at' => now(),
        ]);
        $this->assertNull($guest->password);

        $this->actingAs($guest)
            ->get(route('security.edit'))
            ->assertRedirect(route('password.setup'));
    }

    public function test_event_page_exposes_availability_url_for_polling(): void
    {
        $session = $this->makeSession(3, 4500);

        $this->get(route('events.show', $session->event->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->component('buyer/event')
                ->has('availabilityUrl'));
    }

    public function test_login_honors_safe_local_redirect(): void
    {
        $user = User::factory()->create(['password' => 'segredo123']);

        // Abre o login com o destino de volta (?redirect=), depois autentica.
        $this->get('/login?redirect='.urlencode('/meus-ingressos'));
        $this->post('/login', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect('/meus-ingressos');
    }

    public function test_login_ignores_external_redirect(): void
    {
        $user = User::factory()->create(['password' => 'segredo123']);

        $this->get('/login?redirect='.urlencode('https://evil.example.com'));
        $this->post('/login', ['email' => $user->email, 'password' => 'segredo123'])
            ->assertRedirect(route('tickets.index'));
    }
}
