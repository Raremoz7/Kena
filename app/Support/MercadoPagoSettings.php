<?php

namespace App\Support;

use App\Models\MercadoPagoAccount;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Payments\MercadoPagoOAuth;

/**
 * Credenciais efetivas do Mercado Pago.
 *
 * OAuth desligado (padrão): o que foi salvo no painel (Setting, encriptado) tem
 * prioridade; cai para config/.env quando não há valor salvo.
 * OAuth ligado: só vale a conta conectada; sem conta, não há credencial.
 */
final class MercadoPagoSettings
{
    /** Token para vendas novas. */
    public static function accessToken(): ?string
    {
        if (MercadoPagoOAuth::enabled()) {
            $account = MercadoPagoAccount::current();

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
            $account = Payment::query()
                ->where('gateway_payment_id', $gatewayPaymentId)
                ->whereNotNull('mercadopago_account_id')
                ->first()?->mercadopagoAccount;

            if ($account !== null) {
                return app(MercadoPagoOAuth::class)->freshToken($account);
            }
        }

        return self::accessToken();
    }

    /** Conta que recebe as vendas novas (null com OAuth desligado). */
    public static function currentAccountId(): ?int
    {
        return MercadoPagoOAuth::enabled() ? MercadoPagoAccount::current()?->id : null;
    }

    public static function publicKey(): ?string
    {
        if (MercadoPagoOAuth::enabled()) {
            return MercadoPagoAccount::current()?->public_key;
        }

        return self::resolve('mp_public_key', 'public_key');
    }

    public static function webhookSecret(): ?string
    {
        return self::resolve('mp_webhook_secret', 'webhook_secret');
    }

    public static function statementDescriptor(): string
    {
        return self::resolve('mp_statement_descriptor', 'statement_descriptor') ?? 'KENA INGRESSOS';
    }

    public static function pixExpirationMinutes(): int
    {
        $saved = Setting::get('mp_pix_expiration');

        return (int) ($saved !== null && $saved !== '' ? $saved : config('kena.mercadopago.pix_expiration_minutes', 30));
    }

    public static function baseUrl(): string
    {
        return (string) config('kena.mercadopago.base_url', 'https://api.mercadopago.com');
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
