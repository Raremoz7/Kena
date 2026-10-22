<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Support\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Digitar no checkout de convidado o e-mail de uma conta leve já existente
 * não pode dar acesso a ela: só entra quem recebe o código no e-mail do dono.
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

    private function reserveAsGuest(EventSession $session, string $email, ?string $code): TestResponse
    {
        $session->loadMissing('event');

        return $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 1),
                'guest' => ['name' => 'Outra Pessoa', 'email' => $email, 'cpf' => self::OTHER_CPF, 'code' => $code],
            ],
        );
    }

    private function assertOwnerUntouched(User $owner): void
    {
        $owner->refresh();
        $this->assertSame('Maria Original', $owner->name);
        $this->assertSame(self::VALID_CPF, $owner->cpf);
    }

    public function test_existing_light_account_without_code_is_not_logged_in(): void
    {
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);

        $this->reserveAsGuest($session, 'MAE@guest.test ', null)->assertSessionHasErrors('guest.code');

        $this->assertGuest();
        $this->assertSame(0, Reservation::count());
        $this->assertOwnerUntouched($owner);
    }

    public function test_wrong_code_does_not_log_in(): void
    {
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);
        $issued = app(EmailVerificationCodeService::class)->issue('mae@guest.test');
        $wrong = $issued === '000000' ? '111111' : '000000';

        $this->reserveAsGuest($session, 'mae@guest.test', $wrong)->assertSessionHasErrors('guest.code');

        $this->assertGuest();
        $this->assertSame(0, Reservation::count());
        $this->assertOwnerUntouched($owner);
    }

    public function test_code_issued_for_another_email_does_not_unlock_the_account(): void
    {
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);
        $attackerCode = app(EmailVerificationCodeService::class)->issue('atacante@guest.test');

        $this->reserveAsGuest($session, 'mae@guest.test', $attackerCode)->assertSessionHasErrors('guest.code');

        $this->assertGuest();
        $this->assertOwnerUntouched($owner);
    }

    public function test_owner_with_valid_code_continues_without_losing_data(): void
    {
        $owner = $this->lightAccount();
        $session = $this->makeSession(1);
        $code = app(EmailVerificationCodeService::class)->issue('mae@guest.test');

        $this->reserveAsGuest($session, 'mae@guest.test', $code)->assertRedirect();

        $this->assertAuthenticatedAs($owner);
        $this->assertSame(1, Reservation::where('user_id', $owner->id)->count());
        // Mesmo provando o e-mail, o formulário não reescreve o cadastro existente.
        $this->assertOwnerUntouched($owner);
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
}
