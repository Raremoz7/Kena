<?php

namespace Tests\Feature\Kena;

use App\Support\QrImage;
use chillerlan\QRCode\QRCode;
use Tests\TestCase;

class KenaQrStyleTest extends TestCase
{
    public function test_gera_um_png_valido(): void
    {
        $png = QrImage::png('KNA-000001.abc1234567.deadbeefcafef00d');

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
    }

    public function test_qr_gerado_continua_legivel_apos_o_estilo(): void
    {
        $token = 'KNA-000001.abc1234567.deadbeefcafef00d';

        $png = QrImage::png($token);

        $result = (new QRCode)->readFromBlob($png);

        $this->assertSame($token, $result->data);
    }

    public function test_funciona_com_tokens_de_tamanhos_diferentes(): void
    {
        foreach (['KNA-1.aaaaaaaaaa.0000000000000000', 'KNA-999999-XL.zzzzzzzzzz.ffffffffffffffff'] as $token) {
            $png = QrImage::png($token);
            $result = (new QRCode)->readFromBlob($png);

            $this->assertSame($token, $result->data, "falhou para o token: {$token}");
        }
    }
}
