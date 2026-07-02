<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Geração de códigos legíveis do domínio (ingressos, pedidos). */
final class Codes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem caracteres ambíguos

    /** Ex.: KNA-7Q2X-J1K9-M4P1 */
    public static function ticket(): string
    {
        return 'KNA-'.self::group().'-'.self::group().'-'.self::group();
    }

    /** Ex.: KNA-ORDER-7Q2XJ1K9 */
    public static function orderReference(): string
    {
        return 'KNA-ORDER-'.self::group().self::group();
    }

    private static function group(): string
    {
        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }

    /** Token opaco para o QR (parte aleatória; a assinatura vem do QrTokenService). */
    public static function random(int $length = 32): string
    {
        return Str::random($length);
    }
}
