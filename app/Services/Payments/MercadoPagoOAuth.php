<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\MercadoPagoSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Conectar com Mercado Pago": fluxo OAuth Authorization Code (com PKCE S256),
 * troca do code por token e renovação pelo refresh_token.
 * Doc: developers/pt/docs/security/oauth/creation e /renewal.
 */
class MercadoPagoOAuth
{
    public static function enabled(): bool
    {
        return (bool) config('kena.mercadopago.oauth.enabled', false);
    }

    /** Client ID e Secret da aplicação presentes. */
    public static function configured(): bool
    {
        return filled(config('kena.mercadopago.oauth.client_id'))
            && filled(config('kena.mercadopago.oauth.client_secret'));
    }

    public function redirectUri(): string
    {
        $configured = config('kena.mercadopago.oauth.redirect_uri');

        return filled($configured) ? (string) $configured : route('admin.settings.mp.callback');
    }

    /**
     * Gera state + PKCE para uma tentativa. Os dois ficam na sessão do
     * organizador; o state nunca leva dado sensível.
     *
     * @return array{state: string, verifier: string|null}
     */
    public function newAttempt(): array
    {
        return [
            'state' => Str::random(40),
            'verifier' => config('kena.mercadopago.oauth.pkce', true) ? Str::random(64) : null,
        ];
    }

    public function authorizationUrl(string $state, ?string $verifier): string
    {
        $query = [
            'client_id' => (string) config('kena.mercadopago.oauth.client_id'),
            'response_type' => 'code',
            'platform_id' => 'mp',
            'state' => $state,
            'redirect_uri' => $this->redirectUri(),
        ];
        if ($verifier !== null) {
            $query['code_challenge'] = self::challenge($verifier);
            $query['code_challenge_method'] = 'S256';
        }

        return config('kena.mercadopago.oauth.auth_url').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** S256: BASE64URL(SHA256(verifier)), sem padding (RFC 7636 §4.2). */
    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /** Troca o code (vale 10 min, uso único) pelo token e salva a conta. */
    public function connect(string $code, ?string $verifier, ?int $panelUserId): MercadoPagoAccount
    {
        $body = [
            'client_id' => (string) config('kena.mercadopago.oauth.client_id'),
            'client_secret' => (string) config('kena.mercadopago.oauth.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ];
        if ($verifier !== null) {
            $body['code_verifier'] = $verifier;
        }
        if (config('kena.mercadopago.oauth.test_token', false)) {
            $body['test_token'] = 'true';
        }

        $data = $this->tokenRequest($body);
        $organizationId = CurrentOrganization::id() ?? Organization::DEFAULT_ID;

        // Uma conta do Mercado Pago recebe por um organizador só: senão a venda
        // de um cairia na conta do outro.
        $account = MercadoPagoAccount::withoutGlobalScopes()->firstOrNew(['mp_user_id' => (string) $data['user_id']]);
        if ($account->exists && $account->organization_id !== $organizationId) {
            throw new PaymentException('Esta conta do Mercado Pago já está conectada a outro organizador. Use outra conta.');
        }
        $account->organization_id = $organizationId;
        $account->fill($this->tokenFields($data) + [
            'nickname' => $this->nickname((string) $data['access_token']),
            'connected_by' => $panelUserId,
            'connected_at' => now(),
            'disconnected_at' => null,
        ])->save();

        // Uma conta recebendo por vez: as outras deixam de receber vendas novas
        // (os pagamentos antigos delas continuam estornáveis).
        MercadoPagoAccount::withoutGlobalScopes()->connected()
            ->where('organization_id', $organizationId)
            ->whereKeyNot($account->id)
            ->update(['disconnected_at' => now()]);

        return $account;
    }

    /** Renova pelo refresh_token. O MP devolve um refresh_token novo a cada vez. */
    public function refresh(MercadoPagoAccount $account): MercadoPagoAccount
    {
        if (blank($account->refresh_token)) {
            throw new PaymentException('Conta do Mercado Pago sem refresh_token: conecte de novo.');
        }

        $data = $this->tokenRequest([
            'client_id' => (string) config('kena.mercadopago.oauth.client_id'),
            'client_secret' => (string) config('kena.mercadopago.oauth.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $account->refresh_token,
        ]);

        $account->fill($this->tokenFields($data))->save();

        return $account;
    }

    /** Token válido da conta, renovando na hora se já venceu. */
    public function freshToken(MercadoPagoAccount $account): string
    {
        if ($account->isExpired()) {
            $account = $this->refresh($account);
        }

        return $account->access_token;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function tokenRequest(array $body): array
    {
        if (! self::configured()) {
            throw new PaymentException('Client ID/Secret da aplicação do Mercado Pago não configurados.');
        }

        $response = Http::baseUrl(MercadoPagoSettings::baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post('/oauth/token', $body);

        if ($response->failed() || blank($response->json('access_token')) || blank($response->json('user_id'))) {
            // Nunca loga o corpo enviado (tem client_secret e code).
            Log::warning('Mercado Pago recusou o OAuth.', [
                'grant_type' => $body['grant_type'],
                'status' => $response->status(),
                'error' => $response->json('error'),
                'message' => $response->json('message'),
            ]);

            throw new PaymentException('O Mercado Pago não autorizou a conexão. Tente conectar de novo.');
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function tokenFields(array $data): array
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'public_key' => isset($data['public_key']) ? (string) $data['public_key'] : null,
            'scope' => isset($data['scope']) ? (string) $data['scope'] : null,
            'live_mode' => isset($data['live_mode']) ? (bool) $data['live_mode'] : null,
            'expires_at' => $expiresIn !== null ? now()->addSeconds($expiresIn) : null,
        ];
    }

    /** Apelido para o painel mostrar qual conta está conectada (falha não bloqueia). */
    private function nickname(string $accessToken): ?string
    {
        try {
            $me = Http::baseUrl(MercadoPagoSettings::baseUrl())
                ->withToken($accessToken)->acceptJson()->timeout(10)->get('/users/me');

            return $me->successful() ? ($me->json('nickname') ?? $me->json('email')) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
