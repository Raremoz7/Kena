<?php

namespace Tests\Feature\Kena;

use App\Mail\EmailVerificationCodeMail;
use App\Support\EmailDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** E-mail com domínio que não existe ou digitado errado. */
class EmailDomainTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        EmailDomain::resolveUsing(fn (string $domain): bool => $domain !== 'naoexiste.com.br' && $domain !== 'hotmail.con');
    }

    public function test_code_is_not_sent_to_a_domain_that_does_not_exist(): void
    {
        Mail::fake();

        $this->postJson(route('email-code.send'), ['email' => 'ana@naoexiste.com.br'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Esse e-mail não existe: confira se o endereço depois do @ está certo.');

        Mail::assertNotSent(EmailVerificationCodeMail::class);
    }

    public function test_typo_in_a_common_domain_suggests_the_right_one(): void
    {
        $this->postJson(route('email-code.send'), ['email' => 'ana@hotmail.con'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Esse e-mail não existe. Você quis dizer ana@hotmail.com?');
    }

    public function test_real_domains_still_get_the_code(): void
    {
        Mail::fake();

        $this->postJson(route('email-code.send'), ['email' => 'ana@escola.com.br'])->assertOk();
        $this->postJson(route('email-code.send'), ['email' => 'pai@gmail.com'])->assertOk();

        Mail::assertSent(EmailVerificationCodeMail::class, 2);
    }

    public function test_guest_purchase_rejects_a_domain_that_does_not_exist(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');

        $this->post(route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]), [
            'seats' => $this->availableSeatIds($session, 1),
            'guest' => ['name' => 'Ana', 'email' => 'ana@naoexiste.com.br', 'cpf' => '529.982.247-25', 'code' => '123456'],
        ])->assertSessionHasErrors(['guest.email']);
    }

    public function test_suggestions_for_common_typos(): void
    {
        $this->assertSame('ana@gmail.com', EmailDomain::suggestion('ana@gmial.com'));
        $this->assertSame('ana@gmail.com', EmailDomain::suggestion('ana@gmail.con'));
        $this->assertSame('ana@hotmail.com', EmailDomain::suggestion('ana@hotmal.com'));
        $this->assertSame('ana@yahoo.com.br', EmailDomain::suggestion('ana@yaho.com.br'));
        $this->assertNull(EmailDomain::suggestion('ana@gmail.com'));
        $this->assertNull(EmailDomain::suggestion('ana@escoladedanca.com.br'));
    }
}
