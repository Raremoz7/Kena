<?php

namespace Tests\Feature\Kena;

use App\Models\Reservation;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Support\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'guest' => [
                    'name' => 'João Silva',
                    'email' => 'joao@guest.test',
                    'cpf' => self::VALID_CPF,
                    'code' => app(EmailVerificationCodeService::class)->issue('joao@guest.test'),
                ],
            ],
        );

        $response->assertRedirect();
        $this->assertAuthenticated();

        $user = User::where('email', 'joao@guest.test')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertSame(self::VALID_CPF, $user->cpf);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, Reservation::where('user_id', $user->id)->count());
    }

    public function test_guest_email_that_already_logs_in_is_sent_to_login(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');
        // Conta do Google: o código é para criar conta, não para entrar nela.
        User::factory()->create(['email' => 'has@pass.test', 'google_id' => 'g-1', 'password' => null]);

        $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 1),
                'guest' => [
                    'name' => 'X',
                    'email' => 'has@pass.test',
                    'cpf' => self::VALID_CPF,
                    'code' => app(EmailVerificationCodeService::class)->issue('has@pass.test'),
                ],
            ],
        )->assertSessionHasErrors(['guest.email']);

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
        $user = User::factory()->create(['password' => null]);
        $url = MagicLink::generate($user, now()->addDays(10));

        $this->get($url)->assertOk();
        $this->post($url)->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($user);
    }
}
