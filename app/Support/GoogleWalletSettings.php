<?php

namespace App\Support;

use App\Models\Setting;

/** Credenciais do Google Wallet (salvas no painel, encriptadas). */
final class GoogleWalletSettings
{
    public static function issuerId(): ?string
    {
        return Setting::get('gw_issuer_id');
    }

    public static function classId(): ?string
    {
        return Setting::get('gw_class_id');
    }

    public static function serviceAccountEmail(): ?string
    {
        return Setting::get('gw_sa_email');
    }

    public static function privateKey(): ?string
    {
        return Setting::get('gw_private_key');
    }

    public static function isConfigured(): bool
    {
        return filled(self::issuerId())
            && filled(self::classId())
            && filled(self::serviceAccountEmail())
            && filled(self::privateKey());
    }
}
