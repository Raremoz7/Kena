<?php

namespace App\Services\Payments;

use App\Support\MercadoPagoSettings;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico da integração com o Mercado Pago — só leitura, não cria cobrança
 * nem deixa registro na conta. Cada checagem devolve status e uma mensagem que
 * diz o que fazer, não só que falhou.
 *
 * @phpstan-type Check array{key: string, label: string, status: string, detail: string}
 */
class MercadoPagoDiagnostic
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /**
     * @return array{ok: bool, checks: list<Check>}
     */
    public function run(): array
    {
        $checks = [];

        $token = MercadoPagoSettings::accessToken();
        $publicKey = MercadoPagoSettings::publicKey();

        // 1. Token presente
        if (blank($token)) {
            $checks[] = $this->check('token', 'Access Token', self::FAIL,
                'Não configurado. Preencha o Access Token abaixo ou defina MP_ACCESS_TOKEN no .env.');

            return $this->result($checks);
        }

        // 2. Token aceito pelo Mercado Pago
        $account = null;
        try {
            $response = Http::baseUrl(MercadoPagoSettings::baseUrl())
                ->withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get('/users/me');

            if ($response->status() === 401) {
                $checks[] = $this->check('token', 'Access Token', self::FAIL,
                    'O Mercado Pago recusou o token (401). Ele foi revogado ou copiado incompleto — gere outro em Suas integrações › Credenciais.');

                return $this->result($checks);
            }

            if ($response->failed()) {
                $detalhe = $response->json('message') ?? $response->body();
                $checks[] = $this->check('token', 'Access Token', self::FAIL,
                    "O Mercado Pago respondeu {$response->status()}: ".mb_substr((string) $detalhe, 0, 200));

                return $this->result($checks);
            }

            $account = $response->json();
            $apelido = $account['nickname'] ?? $account['email'] ?? 'conta sem apelido';
            $checks[] = $this->check('token', 'Access Token', self::OK,
                "Válido — conectado à conta {$apelido} (ID {$account['id']}).");
        } catch (\Throwable $e) {
            $checks[] = $this->check('token', 'Access Token', self::FAIL,
                'Não foi possível falar com a API do Mercado Pago: '.$e->getMessage());

            return $this->result($checks);
        }

        // 3. Ambiente das credenciais — a armadilha que faz "vender" sem receber
        $checks[] = $this->environmentCheck($token, $publicKey);

        // 4. Public Key
        $checks[] = blank($publicKey)
            ? $this->check('public_key', 'Public Key', self::FAIL,
                'Não configurada. Sem ela o checkout não carrega o formulário de cartão.')
            : $this->check('public_key', 'Public Key', self::OK, 'Configurada.');

        // 5. Conta habilitada a receber
        $checks[] = $this->accountCheck($account);

        // 6. Webhook
        $checks[] = $this->webhookSecretCheck();
        $checks[] = $this->webhookUrlCheck();

        return $this->result($checks);
    }

    /**
     * Credencial de teste em produção aceita o pagamento e nunca credita —
     * some do radar porque a venda "funciona".
     *
     * @return Check
     */
    private function environmentCheck(string $token, ?string $publicKey): array
    {
        $tokenTeste = str_starts_with($token, 'TEST-');
        $keyTeste = filled($publicKey) && str_starts_with((string) $publicKey, 'TEST-');
        $producao = app()->isProduction();

        if ($tokenTeste && $producao) {
            return $this->check('ambiente', 'Ambiente das credenciais', self::FAIL,
                'Credenciais de TESTE em produção: os pagamentos são aceitos mas o dinheiro nunca entra. Troque pelas credenciais de produção (APP_USR-).');
        }

        if (filled($publicKey) && $tokenTeste !== $keyTeste) {
            return $this->check('ambiente', 'Ambiente das credenciais', self::FAIL,
                'Access Token e Public Key são de ambientes diferentes (um é TEST-, o outro não). O checkout falha ao criar o pagamento.');
        }

        if ($tokenTeste) {
            return $this->check('ambiente', 'Ambiente das credenciais', self::WARN,
                'Credenciais de teste — ok fora de produção, mas não recebem dinheiro de verdade.');
        }

        return $this->check('ambiente', 'Ambiente das credenciais', self::OK,
            'Credenciais de produção.');
    }

    /**
     * @param  array<string, mixed>|null  $account
     * @return Check
     */
    private function accountCheck(?array $account): array
    {
        $siteId = $account['site_id'] ?? null;

        if ($siteId !== null && $siteId !== 'MLB') {
            return $this->check('conta', 'Conta Mercado Pago', self::WARN,
                "A conta é do site {$siteId}, não do Brasil (MLB). Pix e parcelamento podem não funcionar como esperado.");
        }

        $status = $account['status']['site_status'] ?? null;
        if ($status !== null && $status !== 'active') {
            return $this->check('conta', 'Conta Mercado Pago', self::FAIL,
                "A conta está com status '{$status}' no Mercado Pago e pode não conseguir receber pagamentos.");
        }

        return $this->check('conta', 'Conta Mercado Pago', self::OK,
            'Ativa e habilitada a receber no Brasil.');
    }

    /** @return Check */
    private function webhookSecretCheck(): array
    {
        if (blank(MercadoPagoSettings::webhookSecret())) {
            return app()->isProduction()
                ? $this->check('webhook_secret', 'Webhook Secret', self::FAIL,
                    'Sem segredo, qualquer um pode forjar uma notificação de "pagamento aprovado" e emitir ingresso sem pagar. Copie a chave secreta em Suas integrações › Webhooks.')
                : $this->check('webhook_secret', 'Webhook Secret', self::WARN,
                    'Não configurado. Fora de produção passa, mas é obrigatório antes de vender.');
        }

        return $this->check('webhook_secret', 'Webhook Secret', self::OK,
            'Configurado — as notificações são validadas por assinatura.');
    }

    /** @return Check */
    private function webhookUrlCheck(): array
    {
        $url = route('webhooks.mercadopago');

        if (! str_starts_with($url, 'https://')) {
            return $this->check('webhook_url', 'URL do webhook', self::WARN,
                "A URL gerada é {$url}. O Mercado Pago só envia notificação para HTTPS — ajuste o APP_URL no servidor.");
        }

        // Bate na própria URL: se não responder, o MP também não alcança.
        try {
            $response = Http::timeout(10)->post($url, []);

            // Sem assinatura válida a rota deve recusar — o que prova que ela
            // existe, está publicada e valida a origem.
            return in_array($response->status(), [400, 401, 403, 422], true)
                ? $this->check('webhook_url', 'URL do webhook', self::OK,
                    "Publicada e recusando chamada sem assinatura ({$response->status()}), como deve.")
                : $this->check('webhook_url', 'URL do webhook', self::WARN,
                    "Respondeu {$response->status()} para uma chamada sem assinatura. Esperado 400/401/403.");
        } catch (\Throwable $e) {
            return $this->check('webhook_url', 'URL do webhook', self::FAIL,
                "Não foi possível alcançar {$url} pela internet — o Mercado Pago também não vai conseguir avisar sobre os pagamentos. ".$e->getMessage());
        }
    }

    /** @return Check */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /**
     * @param  list<Check>  $checks
     * @return array{ok: bool, checks: list<Check>}
     */
    private function result(array $checks): array
    {
        $ok = ! collect($checks)->contains(fn (array $c): bool => $c['status'] === self::FAIL);

        return ['ok' => $ok, 'checks' => $checks];
    }
}
