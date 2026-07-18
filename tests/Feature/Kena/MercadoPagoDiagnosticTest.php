<?php

namespace Tests\Feature\Kena;

use App\Models\PanelUser;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\MercadoPagoDiagnostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O diagnóstico existe para dizer POR QUE a integração não funciona — cada
 * teste aqui trava uma causa de falha real e a mensagem que a explica.
 */
class MercadoPagoDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    private function statusDe(array $resultado, string $chave): ?string
    {
        foreach ($resultado['checks'] as $check) {
            if ($check['key'] === $chave) {
                return $check['status'];
            }
        }

        return null;
    }

    private function detalheDe(array $resultado, string $chave): string
    {
        foreach ($resultado['checks'] as $check) {
            if ($check['key'] === $chave) {
                return $check['detail'];
            }
        }

        return '';
    }

    private function contaValida(): void
    {
        Http::fake([
            '*/users/me' => Http::response([
                'id' => 123, 'nickname' => 'KENA_TESTE', 'site_id' => 'MLB',
                'status' => ['site_status' => 'active'],
            ]),
            '*' => Http::response([], 401),
        ]);
    }

    public function test_sem_token_falha_e_nao_chama_a_api(): void
    {
        Http::fake();
        config()->set('kena.mercadopago.access_token', null);

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertFalse($resultado['ok']);
        $this->assertSame(MercadoPagoDiagnostic::FAIL, $this->statusDe($resultado, 'token'));
        Http::assertNothingSent();
    }

    public function test_token_recusado_reporta_401_com_orientacao(): void
    {
        Setting::put('mp_access_token', 'APP_USR-invalido');
        Http::fake(['*/users/me' => Http::response(['message' => 'invalid_token'], 401)]);

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('401', $this->detalheDe($resultado, 'token'));
    }

    public function test_erro_da_api_devolve_a_mensagem_exata_do_mercado_pago(): void
    {
        Setting::put('mp_access_token', 'APP_USR-x');
        Http::fake(['*/users/me' => Http::response(['message' => 'forbidden scopes'], 403)]);

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertStringContainsString('forbidden scopes', $this->detalheDe($resultado, 'token'));
    }

    /** A armadilha silenciosa: aceita o pagamento e nunca credita. */
    public function test_credencial_de_teste_em_producao_e_falha(): void
    {
        app()->detectEnvironment(fn () => 'production');
        Setting::put('mp_access_token', 'TEST-123');
        Setting::put('mp_public_key', 'TEST-abc');
        $this->contaValida();

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertFalse($resultado['ok']);
        $this->assertSame(MercadoPagoDiagnostic::FAIL, $this->statusDe($resultado, 'ambiente'));
        $this->assertStringContainsString('nunca entra', $this->detalheDe($resultado, 'ambiente'));
    }

    public function test_token_e_public_key_de_ambientes_diferentes_falha(): void
    {
        Setting::put('mp_access_token', 'APP_USR-producao');
        Setting::put('mp_public_key', 'TEST-teste');
        $this->contaValida();

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertSame(MercadoPagoDiagnostic::FAIL, $this->statusDe($resultado, 'ambiente'));
    }

    public function test_credencial_de_teste_fora_de_producao_e_apenas_aviso(): void
    {
        Setting::put('mp_access_token', 'TEST-123');
        Setting::put('mp_public_key', 'TEST-abc');
        $this->contaValida();

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertSame(MercadoPagoDiagnostic::WARN, $this->statusDe($resultado, 'ambiente'));
    }

    public function test_conta_suspensa_no_mercado_pago_falha(): void
    {
        Setting::put('mp_access_token', 'APP_USR-x');
        Setting::put('mp_public_key', 'APP_USR-y');
        Http::fake(['*/users/me' => Http::response([
            'id' => 1, 'nickname' => 'X', 'site_id' => 'MLB',
            'status' => ['site_status' => 'suspended'],
        ])]);

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertSame(MercadoPagoDiagnostic::FAIL, $this->statusDe($resultado, 'conta'));
    }

    public function test_credenciais_boas_passam_no_diagnostico(): void
    {
        Setting::put('mp_access_token', 'APP_USR-bom');
        Setting::put('mp_public_key', 'APP_USR-bom');
        Setting::put('mp_webhook_secret', 'segredo');
        Http::fake([
            '*/users/me' => Http::response([
                'id' => 7, 'nickname' => 'KENA', 'site_id' => 'MLB',
                'status' => ['site_status' => 'active'],
            ]),
            // O webhook deve recusar chamada sem assinatura.
            '*' => Http::response([], 401),
        ]);

        $resultado = app(MercadoPagoDiagnostic::class)->run();

        $this->assertTrue($resultado['ok'], 'diagnostico deveria passar: '.json_encode($resultado['checks']));
        $this->assertSame(MercadoPagoDiagnostic::OK, $this->statusDe($resultado, 'token'));
        $this->assertStringContainsString('KENA', $this->detalheDe($resultado, 'token'));
    }

    public function test_organizador_roda_o_diagnostico_pela_rota(): void
    {
        Setting::put('mp_access_token', 'APP_USR-bom');
        $this->contaValida();

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->postJson(route('admin.settings.test-mp'))
            ->assertOk()
            ->assertJsonStructure(['ok', 'checks' => [['key', 'label', 'status', 'detail']]]);
    }

    public function test_comprador_nao_roda_o_diagnostico(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.settings.test-mp'))
            // Fora de api/*, o app redireciona em vez de responder 401.
            ->assertRedirect(route('painel.login'));
    }
}
