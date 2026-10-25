<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Setting;
use App\Models\User;
use App\Services\CourtesyService;
use App\Services\OrderService;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/**
 * Correções da revisão de pagamento: o painel não pode derrubar o SMTP do .env
 * com campos em branco; débito é à vista; Pix também pede CPF (menos em R$ 0).
 */
class MailAndPaymentFixesTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_blank_panel_username_and_password_keep_the_env_credentials(): void
    {
        config([
            'mail.mailers.smtp.username' => 'env-user@smtp-brevo.com',
            'mail.mailers.smtp.password' => 'env-secret',
        ]);

        // Exatamente o que o painel gravou em produção: servidor sim, usuário vazio, sem senha.
        $this->actingAs(PanelUser::factory()->create(), 'painel')->post('/painel/config', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp-relay.brevo.com',
            'mail_port' => 587,
            'mail_username' => '',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'no-reply@somo.tec.br',
            'mail_from_name' => 'Kena',
        ])->assertRedirect();

        MailSettings::apply();

        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.smtp.host'));
        $this->assertSame('env-user@smtp-brevo.com', config('mail.mailers.smtp.username'));
        $this->assertSame('env-secret', config('mail.mailers.smtp.password'));
    }

    public function test_panel_values_still_override_when_filled(): void
    {
        config(['mail.mailers.smtp.username' => 'env-user', 'mail.mailers.smtp.password' => 'env-secret']);
        Setting::put('mail_host', 'smtp.outro.com');
        Setting::put('mail_username', 'painel-user');
        Setting::put('mail_password', 'painel-secret');

        MailSettings::apply();

        $this->assertSame('painel-user', config('mail.mailers.smtp.username'));
        $this->assertSame('painel-secret', config('mail.mailers.smtp.password'));
    }

    public function test_settings_form_shows_the_env_username_and_password_as_configured(): void
    {
        config(['mail.mailers.smtp.username' => 'env-user', 'mail.mailers.smtp.password' => 'env-secret']);

        $display = MailSettings::display();

        $this->assertSame('env-user', $display['username']);
        $this->assertTrue($display['passwordConfigured']);
    }

    /** @return array<string, mixed> corpo enviado ao MP */
    private function chargeWith(?string $paymentMethodId, int $installments): array
    {
        config(['kena.mercadopago.access_token' => 'TEST-token']);
        Http::fake(['*/v1/payments' => Http::response(['id' => 1, 'status' => 'approved', 'status_detail' => 'accredited'])]);

        $session = $this->makeSession(1);
        $reservation = app(SeatReservationService::class)->hold($session, User::factory()->create(), $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);

        app(MercadoPagoGateway::class)->chargeCard($order, 'tok', $installments, $paymentMethodId, 'p@x.test', '52998224725');

        $sent = [];
        Http::assertSent(function ($request) use (&$sent): bool {
            $sent = $request->data();

            return str_ends_with($request->url(), '/v1/payments');
        });

        return $sent;
    }

    public function test_debit_card_is_always_charged_at_once(): void
    {
        $this->assertSame(1, $this->chargeWith('debelo', 3)['installments']);
    }

    public function test_credit_card_keeps_the_chosen_installments(): void
    {
        $this->assertSame(3, $this->chargeWith('visa', 3)['installments']);
    }

    public function test_pix_requires_cpf_when_the_buyer_has_none(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $session = $this->makeSession(1);
        $user = User::factory()->create(['cpf' => null]);
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));

        $this->actingAs($user)->postJson(route('checkout.pay', $reservation), ['method' => 'pix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        $this->actingAs($user)->postJson(route('checkout.pay', $reservation), ['method' => 'pix', 'document' => '529.982.247-25'])
            ->assertOk();
    }

    public function test_pix_always_asks_the_payer_cpf_and_keeps_the_account_cpf(): void
    {
        $gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $gateway);
        $session = $this->makeSession(1);
        $user = User::factory()->create(['cpf' => '11144477735']);
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));

        // Mesmo com CPF na conta, o Pix pede o CPF de quem paga.
        $this->actingAs($user)->postJson(route('checkout.pay', $reservation), ['method' => 'pix'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document');

        // Quem paga pode ser outra pessoa: o CPF vai pro Mercado Pago e a conta segue igual.
        $this->actingAs($user)->postJson(route('checkout.pay', $reservation), ['method' => 'pix', 'document' => '529.982.247-25'])
            ->assertOk();
        $this->assertSame('52998224725', $gateway->lastPixDoc);
        $this->assertSame('11144477735', $user->fresh()?->cpf);
    }

    public function test_json_endpoints_answer_validation_errors_as_json_not_redirect(): void
    {
        // Antes: 302 para "/", que o fetch do front tratava como sucesso.
        $this->postJson(route('email-code.send'), ['email' => 'não-é-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $session = $this->makeSession(1);
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->postJson(route('admin.seats.issue', $session), ['tickets' => [['session_seat_id' => 1, 'name' => 'X', 'email' => 'ruim']]])
            ->assertStatus(422);
    }

    public function test_free_courtesy_does_not_ask_for_cpf(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $session = $this->makeSession(2);
        $coupon = app(CourtesyService::class)->generate($session->event, [['name' => 'Ana', 'group' => null]], 2, null, 'QN')->first();
        $user = User::factory()->create(['cpf' => null]);
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 2));

        $this->actingAs($user)
            ->postJson(route('checkout.pay', $reservation), ['method' => 'pix', 'coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJsonPath('status', Order::STATUS_PAID);
    }
}
