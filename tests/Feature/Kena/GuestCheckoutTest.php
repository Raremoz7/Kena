<?php

namespace Tests\Feature\Kena;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';

    public function test_guest_reserve_creates_passwordless_account_and_holds(): void
    {
        $session = $this->makeSession(2);
        $session->load('event');
        $ids = $this->availableSeatIds($session, 2);

        $response = $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $ids,
                'guest' => ['name' => 'João Silva', 'email' => 'joao@guest.test', 'cpf' => self::VALID_CPF],
            ],
        );

        $response->assertRedirect();
        $this->assertAuthenticated();

        $user = User::where('email', 'joao@guest.test')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertSame(User::ROLE_BUYER, $user->role);
        $this->assertSame(self::VALID_CPF, $user->cpf);
        $this->assertSame(1, Reservation::where('user_id', $user->id)->count());
    }

    public function test_guest_email_with_password_account_must_log_in(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');
        User::factory()->create(['email' => 'has@pass.test']); // factory define senha

        $response = $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 1),
                'guest' => ['name' => 'X', 'email' => 'has@pass.test', 'cpf' => self::VALID_CPF],
            ],
        );

        $response->assertSessionHasErrors('guest.email');
        $this->assertGuest();
    }

    public function test_invalid_cpf_is_rejected(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');

        $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 1),
                'guest' => ['name' => 'X', 'email' => 'x@guest.test', 'cpf' => '11111111111'],
            ],
        )->assertSessionHasErrors('guest.cpf');
    }

    public function test_magic_link_logs_in_and_redirects_to_tickets(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute('magic-login', now()->addDay(), ['user' => $user->id]);

        $this->get($url)->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($user);
    }
}
