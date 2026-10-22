<?php

namespace Tests\Feature\Kena;

use App\Mail\EmailVerificationCodeMail;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    private function codes(): EmailVerificationCodeService
    {
        return app(EmailVerificationCodeService::class);
    }

    public function test_send_endpoint_mails_a_six_digit_code_that_verifies(): void
    {
        Mail::fake();

        $this->postJson(route('email-code.send'), ['email' => 'Pai@Escola.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Enviamos um código de 6 dígitos para pai@escola.test.');

        $sent = null;
        Mail::assertSent(EmailVerificationCodeMail::class, function (EmailVerificationCodeMail $m) use (&$sent): bool {
            $sent = $m->code;

            return $m->hasTo('pai@escola.test');
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $sent);
        $this->assertTrue($this->codes()->verify('pai@escola.test', (string) $sent));
    }

    public function test_send_endpoint_answers_the_same_for_existing_accounts(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'existe@escola.test']);

        $existing = $this->postJson(route('email-code.send'), ['email' => 'existe@escola.test'])->assertOk();
        $missing = $this->postJson(route('email-code.send'), ['email' => 'novo@escola.test'])->assertOk();

        $this->assertSame(
            str_replace('existe@', 'X@', $existing->json('message')),
            str_replace('novo@', 'X@', $missing->json('message')),
        );
    }

    public function test_send_is_limited_per_email(): void
    {
        Mail::fake();

        for ($i = 0; $i < EmailVerificationCodeService::SENDS_PER_WINDOW; $i++) {
            $this->postJson(route('email-code.send'), ['email' => 'pai@escola.test'])->assertOk();
        }
        $this->postJson(route('email-code.send'), ['email' => 'pai@escola.test'])->assertStatus(429);

        Mail::assertSentCount(EmailVerificationCodeService::SENDS_PER_WINDOW);
    }

    public function test_code_is_single_use(): void
    {
        $code = $this->codes()->issue('pai@escola.test');

        $this->assertTrue($this->codes()->verify('pai@escola.test', $code));
        $this->assertFalse($this->codes()->verify('pai@escola.test', $code));
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        $old = $this->codes()->issue('pai@escola.test');
        $new = $this->codes()->issue('pai@escola.test');

        if ($old !== $new) {
            $this->assertFalse($this->codes()->verify('pai@escola.test', $old));
        }
        $this->assertTrue($this->codes()->verify('pai@escola.test', $new));
    }

    public function test_code_expires(): void
    {
        $code = $this->codes()->issue('pai@escola.test');

        $this->travel(EmailVerificationCodeService::TTL_MINUTES + 1)->minutes();

        $this->assertFalse($this->codes()->verify('pai@escola.test', $code));
    }

    public function test_too_many_wrong_attempts_kill_the_code(): void
    {
        $code = $this->codes()->issue('pai@escola.test');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < EmailVerificationCodeService::MAX_ATTEMPTS; $i++) {
            $this->assertFalse($this->codes()->verify('pai@escola.test', $wrong));
        }

        // Mesmo o código certo não vale mais: é preciso pedir outro.
        $this->assertFalse($this->codes()->verify('pai@escola.test', $code));
    }

    public function test_code_is_tied_to_the_email_and_case_insensitive(): void
    {
        $code = $this->codes()->issue('Pai@Escola.test');

        $this->assertFalse($this->codes()->verify('outro@escola.test', $code));
        $this->assertTrue($this->codes()->verify(' pai@escola.TEST ', $code));
    }

    public function test_code_is_not_stored_in_plain_text(): void
    {
        $code = $this->codes()->issue('pai@escola.test');

        $entry = Cache::get('email-code:'.hash('sha256', 'pai@escola.test'));

        $this->assertIsArray($entry);
        $this->assertStringNotContainsString($code, json_encode($entry, JSON_THROW_ON_ERROR));
    }

    public function test_mail_shows_the_code(): void
    {
        $html = (new EmailVerificationCodeMail('482913', EmailVerificationCodeService::TTL_MINUTES))->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString(EmailVerificationCodeService::TTL_MINUTES.' minutos', $html);
    }
}
