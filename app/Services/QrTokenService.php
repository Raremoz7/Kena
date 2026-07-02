<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Tokens de QR assinados (HMAC). Formato: "<code>.<nonce>.<sig>".
 * A validação no check-in confere a assinatura e casa com o ticket no banco.
 */
class QrTokenService
{
    public function issue(string $ticketCode): string
    {
        $payload = $ticketCode.'.'.Str::random(10);

        return $payload.'.'.$this->signature($payload);
    }

    public function verify(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        [$code, $nonce, $sig] = $parts;

        return hash_equals($this->signature($code.'.'.$nonce), $sig);
    }

    public function codeFromToken(string $token): ?string
    {
        $parts = explode('.', $token);

        return $parts[0];
    }

    private function signature(string $payload): string
    {
        $secret = (string) (config('kena.qr_secret') ?: config('app.key'));

        return substr(hash_hmac('sha256', $payload, $secret), 0, 16);
    }
}
