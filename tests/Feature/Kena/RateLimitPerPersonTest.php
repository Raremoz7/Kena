<?php

namespace Tests\Feature\Kena;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Limites contados por pessoa, não por IP: muitos pais atrás do mesmo IP
 * (operadora de celular, Wi-Fi da escola) não podem se bloquear entre si.
 */
class RateLimitPerPersonTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private const SAME_IP = '200.150.10.20';

    /** @return array<string, mixed> */
    private function guestAttempt(string $email): array
    {
        return [
            'seats' => [1],
            'guest' => ['name' => 'Pai', 'email' => $email, 'cpf' => '52998224725', 'code' => '000000'],
        ];
    }

    public function test_many_parents_behind_the_same_ip_are_not_blocked_on_reserve(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');
        $url = route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]);

        // Antes o limite era 20/min por IP: o 21º pai via "Too Many Requests".
        for ($i = 1; $i <= 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SAME_IP])
                ->post($url, $this->guestAttempt("pai{$i}@escola.test"))
                ->assertStatus(302); // código errado → volta com erro de validação, não 429
        }
    }

    public function test_the_same_person_retrying_too_fast_gets_a_portuguese_message(): void
    {
        $session = $this->makeSession(1);
        $session->load('event');
        $url = route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]);

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SAME_IP])->post($url, $this->guestAttempt('mesmo@escola.test'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => self::SAME_IP])
            ->from($url)
            ->post($url, $this->guestAttempt('mesmo@escola.test'))
            ->assertRedirect($url)
            ->assertSessionHasErrors(['seats' => AppServiceProvider::RATE_LIMIT_MESSAGE]);
    }

    public function test_many_parents_behind_the_same_ip_can_ask_for_email_codes(): void
    {
        Mail::fake();

        // Antes o limite era 10/min por IP.
        for ($i = 1; $i <= 20; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => self::SAME_IP])
                ->postJson(route('email-code.send'), ['email' => "mae{$i}@escola.test"])
                ->assertOk();
        }
    }

    public function test_email_code_limit_answers_json_in_portuguese(): void
    {
        Mail::fake();

        // Sem e-mail válido a validação recusa, mas a tentativa conta no limite da pessoa.
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('email-code.send'), ['email' => 'nao-e-email']);
        }

        $this->postJson(route('email-code.send'), ['email' => 'nao-e-email'])
            ->assertStatus(429)
            ->assertJsonPath('message', AppServiceProvider::RATE_LIMIT_MESSAGE);
    }
}
