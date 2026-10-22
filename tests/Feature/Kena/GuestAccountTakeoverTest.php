<?php

namespace Tests\Feature\Kena;

use App\Mail\GuestAccessLinkMail;
use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\User;
use App\Support\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Digitar no checkout de convidado o e-mail de uma conta leve já existente
 * não pode dar acesso a ela: o dono recebe um magic-link e só ele continua.
 */
class GuestAccountTakeoverTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private const VALID_CPF = '52998224725';

    private const OTHER_CPF = '11144477735';

    private function lightAccount(): User
    {
        return User::factory()->create([
            'email' => 'mae@guest.test',
            'name' => 'Maria Original',
            'cpf' => self::VALID_CPF,
            'password' => null,
            'google_id' => null,
        ]);
    }

    private function reserveAsGuest(EventSession $session, string $email): TestResponse
    {
        $session->loadMissing('event');

        return $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 1),
                'guest' => ['name' => 'Outra Pessoa', 'email' => $email, 'cpf' => self::OTHER_CPF],
            ],
        );
    }

    public function test_existing_light_account_is_not_logged_in_and_gets_access_link(): void
    {
        Mail::fake();
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);

        $this->reserveAsGuest($session, 'MAE@guest.test ')->assertSessionHasErrors('guest.email');

        $this->assertGuest();
        $this->assertSame(0, Reservation::count());
        Mail::assertQueued(GuestAccessLinkMail::class, fn (GuestAccessLinkMail $m): bool => $m->hasTo($owner->email));

        // Dados do dono não são sobrescritos por quem digitou o e-mail.
        $owner->refresh();
        $this->assertSame('Maria Original', $owner->name);
        $this->assertSame(self::VALID_CPF, $owner->cpf);
    }

    public function test_access_link_logs_owner_in_and_returns_to_seats(): void
    {
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);
        $session->load('event');
        $seatsPath = route('sessions.seats', ['slug' => $session->event->slug, 'session' => $session->id], false);

        $html = (new GuestAccessLinkMail($owner, $session))->render();
        preg_match('/href="([^"]*\/entrar\/[^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'e-mail deve conter o link de acesso');

        $this->get(html_entity_decode($m[1]))->assertRedirect($seatsPath);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_access_link_is_single_use(): void
    {
        $owner = $this->lightAccount();
        $url = MagicLink::forCheckout($owner, '/eventos');

        $this->get($url)->assertRedirect('/eventos');
        auth()->logout();

        $this->get($url)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_tampered_redirect_breaks_the_signature(): void
    {
        $owner = $this->lightAccount();
        $url = MagicLink::forCheckout($owner, '/eventos');

        $this->get(str_replace('redirect=%2Feventos', 'redirect=%2F%2Fevil.test', $url))->assertForbidden();
        $this->assertGuest();
    }

    public function test_access_link_expires(): void
    {
        $owner = $this->lightAccount();
        $url = MagicLink::forCheckout($owner, '/eventos');

        $this->travel(MagicLink::CHECKOUT_TTL_MINUTES + 1)->minutes();

        $this->get($url)->assertForbidden();
        $this->assertGuest();
    }

    public function test_safe_redirect_only_accepts_local_paths(): void
    {
        $this->assertSame('/e/x/sessoes/1', MagicLink::safeRedirect('/e/x/sessoes/1'));
        $this->assertNull(MagicLink::safeRedirect('//evil.test'));
        $this->assertNull(MagicLink::safeRedirect('https://evil.test'));
        $this->assertNull(MagicLink::safeRedirect('/\\evil.test'));
        $this->assertNull(MagicLink::safeRedirect(null));
    }

    public function test_access_link_emails_are_rate_limited_per_account(): void
    {
        Mail::fake();
        $this->lightAccount();
        $session = $this->makeSession(1);

        for ($i = 0; $i < 5; $i++) {
            $this->reserveAsGuest($session, 'mae@guest.test')->assertSessionHasErrors('guest.email');
        }

        Mail::assertQueued(GuestAccessLinkMail::class, 3);
    }
}
