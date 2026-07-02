<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Gera o QR de um ingresso como PNG, no estilo Kena, para anexar inline em e-mails. */
final class QrImage
{
    /** Bytes PNG do QR. */
    public static function png(string $data, int $size = 240): string
    {
        $options = new QROptions([
            'eccLevel' => EccLevel::H,
            'outputBase64' => false,
            'bgColor' => [0xF4, 0xEF, 0xE7],
        ]);

        $qrcode = new QRCode($options);
        $qrcode->addByteSegment($data);
        $matrix = $qrcode->getQRMatrix();

        $options->scale = max(4, (int) round($size / $matrix->moduleCount));

        return (new KenaQrRenderer($options, $matrix))->dump();
    }
}
