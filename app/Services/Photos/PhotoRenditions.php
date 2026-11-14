<?php

namespace App\Services\Photos;

use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Gera as versões leves de cada foto a partir do original em alta:
 * a prévia com marca d'água (vista na galeria), a miniatura da grade e o
 * recorte do rosto usado no filtro por pessoa.
 *
 * O original nunca é salvo aqui: ele continua no Google Drive e só é entregue
 * depois do pagamento.
 */
final class PhotoRenditions
{
    // Pequena de propósito: mesmo se alguém apagar a marca com IA, não serve para imprimir.
    private const PREVIEW_SIDE = 1200;

    private const THUMB_SIDE = 720;

    private const FACE_SIDE = 192;

    private const WATERMARK = 'KENA  ·  AMOSTRA';

    /** Linha de aviso desenhada na marca d'água, com o titular dos direitos. */
    public static function notice(string $holder): string
    {
        return "© {$holder}  ·  Uso só com compra  ·  Lei 9.610/98";
    }

    /** Titular padrão (config) para galeria sem nome preenchido. */
    public static function defaultHolder(): string
    {
        return (string) config('kena.photos.copyright_holder', 'Kena');
    }

    /**
     * Prévia grande, com marca d'água forte o bastante para não servir de cópia.
     * `$holder` é o titular do direito autoral: vai no aviso e no XMP.
     *
     * @return array{path: string, width: int, height: int}
     */
    public function preview(GdImage $source, string $path, ?string $holder = null): array
    {
        $holder = $this->holder($holder);
        $image = $this->fit($source, self::PREVIEW_SIDE);
        $this->watermark($image, 0.032, 92, $holder, 0.0185, 2);

        return $this->store($image, $path, 80, $holder);
    }

    /**
     * Miniatura da grade: marca mais discreta, aviso só de vez em quando.
     *
     * @return array{path: string, width: int, height: int}
     */
    public function thumb(GdImage $source, string $path, ?string $holder = null): array
    {
        $holder = $this->holder($holder);
        $image = $this->fit($source, self::THUMB_SIDE);
        $this->watermark($image, 0.036, 104, $holder, 0.026, 4);

        return $this->store($image, $path, 76, $holder);
    }

    private function holder(?string $holder): string
    {
        $holder = trim((string) $holder);

        return $holder !== '' ? $holder : self::defaultHolder();
    }

    /**
     * Recorta o rosto a partir da área no padrão MWG (centro x/y, largura e
     * altura normalizados), em quadrado e com folga em volta para enquadrar.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $region
     * @return array{path: string, width: int, height: int}
     */
    public function face(GdImage $source, array $region, string $path): array
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $side = (int) round(max($region['w'] * $width, $region['h'] * $height) * 1.35);
        $side = max(16, min($side, $width, $height));
        $x = (int) round($region['x'] * $width - $side / 2);
        $y = (int) round($region['y'] * $height - $side / 2);
        $x = max(0, min($x, $width - $side));
        $y = max(0, min($y, $height - $side));

        $face = Gd::canvas(self::FACE_SIDE, self::FACE_SIDE);
        imagecopyresampled($face, $source, 0, 0, $x, $y, self::FACE_SIDE, self::FACE_SIDE, $side, $side);

        return $this->store($face, $path, 82);
    }

    private function fit(GdImage $source, int $longSide): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $longSide / max($width, $height));
        $w = (int) round($width * $scale);
        $h = (int) round($height * $scale);

        $image = Gd::canvas($w, $h);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        return $image;
    }

    /**
     * Texto em diagonal repetido pela foto toda. `alpha` vai de 0 (opaco) a 127
     * (invisível) no GD. Entre as linhas de "KENA · AMOSTRA" entra o aviso de
     * direito autoral (menor), a cada `noticeEvery` linhas.
     */
    private function watermark(GdImage $image, float $sizeRatio, int $alpha, string $holder, float $noticeRatio, int $noticeEvery): void
    {
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(10, (int) round(max($width, $height) * $sizeRatio));

        imagealphablending($image, true);
        $light = Gd::colorAlpha($image, 255, 255, 255, $alpha);
        $shadow = Gd::colorAlpha($image, 0, 0, 0, min(127, $alpha + 14));

        $stepX = (int) round($size * 11);
        $stepY = (int) round($size * 5.2);
        // Posição e ângulo mudam a cada foto: marca idêntica em todas é a mais
        // fácil de remover em lote.
        $shiftX = random_int(0, $stepX);
        $shiftY = random_int(0, $stepY);
        $angle = random_int(22, 34);
        $row = 0;

        $notice = self::notice($holder);
        $noticeSize = max(9, (int) round(max($width, $height) * $noticeRatio));
        $noticeBox = Gd::textBox($noticeSize, $font, $notice);
        $noticeStepX = (int) round(($noticeBox[2] - $noticeBox[0]) * 1.3);
        $noticeLight = Gd::colorAlpha($image, 255, 255, 255, max(0, $alpha - 6));
        $noticeShadow = Gd::colorAlpha($image, 0, 0, 0, min(127, $alpha + 8));

        for ($y = -$height + $shiftY; $y < $height * 2; $y += $stepY, $row++) {
            // O aviso fica no meio entre duas linhas de marca, no mesmo ângulo.
            if ($row % $noticeEvery === $noticeEvery - 1) {
                $noticeOffset = (int) ($noticeStepX / 2) * ($row % 2) + $shiftX;
                $noticeY = $y + (int) ($stepY / 2);

                for ($x = -$width; $x < $width * 2; $x += $noticeStepX) {
                    imagettftext($image, $noticeSize, $angle, $x + $noticeOffset + 1, $noticeY + 1, $noticeShadow, $font, $notice);
                    imagettftext($image, $noticeSize, $angle, $x + $noticeOffset, $noticeY, $noticeLight, $font, $notice);
                }
            }

            $offset = ($row % 2) * (int) ($stepX / 2) + $shiftX;

            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                imagettftext($image, $size, $angle, $x + $offset + 2, $y + 2, $shadow, $font, self::WATERMARK);
                imagettftext($image, $size, $angle, $x + $offset, $y, $light, $font, self::WATERMARK);
            }
        }

        // Selo central maior, atravessando o assunto da foto.
        $big = (int) round($size * 2.4);
        $box = Gd::textBox($big, $font, 'KENA');
        $bw = $box[2] - $box[0];
        $center = Gd::colorAlpha($image, 255, 255, 255, max(0, $alpha - 18));
        imagettftext($image, $big, 0, (int) (($width - $bw) / 2), (int) ($height / 2 + $big / 2), $center, $font, 'KENA');

        $this->noticeBand($image, $font, $notice, $alpha);
    }

    /**
     * Faixa no rodapé com o aviso inteiro, na horizontal e legível de relance
     * (as linhas em diagonal ficam cortadas pelas bordas da foto).
     */
    private function noticeBand(GdImage $image, string $font, string $notice, int $alpha): void
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $ref = Gd::textBox(100, $font, $notice);
        $size = (int) floor(min($width * 0.94 / (($ref[2] - $ref[0]) / 100), max($width, $height) * 0.026));
        $size = max(8, $size);
        $box = Gd::textBox($size, $font, $notice);
        $bandHeight = (int) round($size * 2);

        imagefilledrectangle($image, 0, $height - $bandHeight, $width, $height, Gd::colorAlpha($image, 0, 0, 0, min(127, $alpha - 30)));
        imagettftext(
            $image,
            $size,
            0,
            (int) (($width - ($box[2] - $box[0])) / 2),
            (int) ($height - ($bandHeight - $size) / 2 - $size * 0.18),
            Gd::colorAlpha($image, 255, 255, 255, 12),
            $font,
            $notice,
        );
    }

    /** @return array{path: string, width: int, height: int} */
    private function store(GdImage $image, string $path, int $quality, ?string $holder = null): array
    {
        ob_start();
        imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();

        if ($bytes === '') {
            throw new RuntimeException("Falha ao gerar {$path}");
        }

        // Prévias e miniaturas levam o direito autoral também nos metadados.
        if ($holder !== null) {
            $bytes = WebpMetadata::embedXmp($bytes, WebpMetadata::copyrightPacket($holder, (int) date('Y')));
        }

        Storage::disk(config('kena.photos.disk'))->put($path, $bytes);

        return ['path' => $path, 'width' => imagesx($image), 'height' => imagesy($image)];
    }
}
