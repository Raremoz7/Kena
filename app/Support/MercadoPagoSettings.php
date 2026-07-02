<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Credenciais efetivas do Mercado Pago: o que foi salvo no painel (Setting,
 * encriptado) tem prioridade; cai para config/.env quando não há valor salvo.
 */
final class MercadoPagoSettings
{
    public static function accessToken(): ?string
    {
        return self::resolve('mp_access_token', 'access_token');
    }

    public static function publicKey(): ?string
    {
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
