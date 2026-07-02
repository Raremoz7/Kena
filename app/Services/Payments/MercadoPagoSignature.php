<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * Verifica a assinatura `x-signature` do webhook do Mercado Pago.
 * Manifesto: "id:<data.id>;request-id:<x-request-id>;ts:<ts>;" com HMAC-SHA256.
 */
final class MercadoPagoSignature
{
    public static function verify(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('x-signature', '');
        if ($signature === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        $ts = $parts['ts'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if ($ts === null || $v1 === null) {
            return false;
        }

        $dataId = self::dataId($request);
        $requestId = (string) $request->header('x-request-id', '');
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $computed = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($computed, $v1);
    }

    /** O id do recurso vem no corpo (data.id) e/ou na query; MP usa minúsculas. */
    private static function dataId(Request $request): string
    {
        $id = data_get($request->all(), 'data.id')
            ?? $request->query('data_id')
            ?? $request->query('data.id')
            ?? '';

        return mb_strtolower((string) $id);
    }
}
