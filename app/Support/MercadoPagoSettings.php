<?php

namespace App\Support;

use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\MercadoPagoOAuth;

/**
 * Credenciais efetivas do Mercado Pago.
 *
 * OAuth desligado (padrão): o que foi salvo no painel (Setting, encriptado) tem
 * prioridade; cai para config/.env quando não há valor salvo. Vale para todas
 * as organizações (a venda cai na conta da plataforma).
 * OAuth ligado: vale a conta conectada da organização dona do evento; sem
 * conta, não há credencial.
 *
 * $organizationId null = organização do painel ou, fora dele, a principal.
 */
final class MercadoPagoSettings
{
    /** Token para vendas novas da organização. */
    public static function accessToken(?int $organizationId = null): ?string
    {
        if (MercadoPagoOAuth::enabled()) {
            $account = MercadoPagoAccount::forOrganization($organizationId);

            return $account !== null ? app(MercadoPagoOAuth::class)->freshToken($account) : null;
        }

        return self::resolve('mp_access_token', 'access_token');
    }

    /**
     * Token para operar um pagamento já criado (consulta, estorno, cancelamento):
     * o da conta que o criou, mesmo que ela não seja mais a conectada.
     */
    public static function accessTokenForPayment(string $gatewayPaymentId): ?string
    {
        if (MercadoPagoOAuth::enabled() && $gatewayPaymentId !== '') {
            $payment = Payment::withoutGlobalScopes()
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->first();

            if ($payment !== null && $payment->mercadopago_account_id !== null) {
                $account = MercadoPagoAccount::withoutGlobalScopes()->find($payment->mercadopago_account_id);

                return $account !== null ? app(MercadoPagoOAuth::class)->freshToken($account) : null;
            }

            // Venda de antes de ligar o OAuth: foi criada com a credencial da
            // plataforma, então consulta e estorno continuam nela (não na conta
            // que o organizador conectou depois).
            if ($payment !== null) {
                return self::resolve('mp_access_token', 'access_token');
            }
        }

        return self::accessToken();
    }

    /** Há alguma credencial para operar (reconciliação em segundo plano)? */
    public static function anyCredentials(): bool
    {
        return MercadoPagoOAuth::enabled()
            ? MercadoPagoAccount::withoutGlobalScopes()->exists()
            : filled(self::resolve('mp_access_token', 'access_token'));
    }

    /** Conta que recebe as vendas novas da organização (null com OAuth desligado). */
    public static function currentAccountId(?int $organizationId = null): ?int
    {
        return MercadoPagoOAuth::enabled() ? MercadoPagoAccount::forOrganization($organizationId)?->id : null;
    }

    public static function publicKey(?int $organizationId = null): ?string
    {
        if (MercadoPagoOAuth::enabled()) {
            return MercadoPagoAccount::forOrganization($organizationId)?->public_key;
        }

        return self::resolve('mp_public_key', 'public_key');
    }

    public static function webhookSecret(): ?string
    {
        return self::resolve('mp_webhook_secret', 'webhook_secret');
    }

    /** Nome na fatura do cartão: o da organização ou, sem ele, o global. */
    public static function statementDescriptor(?int $organizationId = null): string
    {
        $own = self::organization($organizationId)?->statement_descriptor;

        return filled($own)
            ? (string) $own
            : (self::resolve('mp_statement_descriptor', 'statement_descriptor') ?? 'KENA INGRESSOS');
    }

    public static function pixExpirationMinutes(?int $organizationId = null): int
    {
        $own = self::organization($organizationId)?->pix_expiration_minutes;
        if ($own !== null && $own > 0) {
            return $own;
        }

        $saved = Setting::get('mp_pix_expiration');

        return (int) ($saved !== null && $saved !== '' ? $saved : config('kena.mercadopago.pix_expiration_minutes', 30));
    }

    public static function baseUrl(): string
    {
        return (string) config('kena.mercadopago.base_url', 'https://api.mercadopago.com');
    }

    private static function organization(?int $organizationId): ?Organization
    {
        $id = $organizationId ?? CurrentOrganization::id();

        return $id !== null ? Organization::find($id) : null;
    }

    private static function resolve(string $settingKey, string $configKey): ?string
    {
        $saved = Setting::get($settingKey);
        if ($saved !== null && $saved !== '') {
            return $saved;
        }

        $fallback = config("kena.mercadopago.{$configKey}");

        return $fallback !== null ? (string) $fallback : null;
    }
}
