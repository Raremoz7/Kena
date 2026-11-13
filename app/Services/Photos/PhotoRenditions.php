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

    /** Prévia grande, com marca d'água forte o bastante para não servir de cópia. */
    public function preview(GdImage $source, string $path): array
    {
        $image = $this->fit($source, self::PREVIEW_SIDE);
        $this->watermark($image, 0.032, 92);

        return $this->store($image, $path, 80);
    }

    /** Miniatura da grade: marca mais discreta, só para não circular solta. */
    public function thumb(GdImage $source, string $path): array
    {
        $image = $this->fit($source, self::THUMB_SIDE);
        $this->watermark($image, 0.036, 104);

        return $this->store($image, $path, 76);
    }

    /**
     * Recorta o rosto a partir da área no padrão MWG (centro x/y, largura e
     * altura normalizados), em quadrado e com folga em volta para enquadrar.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $region
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

        $face = imagecreatetruecolor(self::FACE_SIDE, self::FACE_SIDE);
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

        $image = imagecreatetruecolor($w, $h);
        imagecopyresampled($image, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        return $image;
    }

    /**
     * Texto em diagonal repetido pela foto toda. `alpha` vai de 0 (opaco) a 127
     * (invisível) no GD.
     */
    private function watermark(GdImage $image, float $sizeRatio, int $alpha): void
    {
        $font = resource_path('fonts/DejaVuSans-Bold.ttf');
        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(10, (int) round(max($width, $height) * $sizeRatio));

        imagealphablending($image, true);
        $light = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, min(127, $alpha + 14));

        $stepX = (int) round($size * 11);
        $stepY = (int) round($size * 5.2);
        // Posição e ângulo mudam a cada foto: marca idêntica em todas é a mais
        // fácil de remover em lote.
        $shiftX = random_int(0, $stepX);
        $shiftY = random_int(0, $stepY);
        $angle = random_int(22, 34);
        $row = 0;

        for ($y = -$height + $shiftY; $y < $height * 2; $y += $stepY, $row++) {
            $offset = ($row % 2) * (int) ($stepX / 2) + $shiftX;

            for ($x = -$width; $x < $width * 2; $x += $stepX) {
                imagettftext($image, $size, $angle, $x + $offset + 2, $y + 2, $shadow, $font, self::WATERMARK);
                imagettftext($image, $size, $angle, $x + $offset, $y, $light, $font, self::WATERMARK);
            }
        }

        // Selo central maior, atravessando o assunto da foto.
        $big = (int) round($size * 2.4);
        $box = imagettfbbox($big, 0, $font, 'KENA');
        $bw = $box[2] - $box[0];
        $center = imagecolorallocatealpha($image, 255, 255, 255, max(0, $alpha - 18));
        imagettftext($image, $big, 0, (int) (($width - $bw) / 2), (int) ($height / 2 + $big / 2), $center, $font, 'KENA');
    }

    /** @return array{path: string, width: int, height: int} */
    private function store(GdImage $image, string $path, int $quality): array
    {
        ob_start();
        imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();

        if ($bytes === '') {
            throw new RuntimeException("Falha ao gerar {$path}");
        }

        Storage::disk(config('kena.photos.disk'))->put($path, $bytes);

        return ['path' => $path, 'width' => imagesx($image), 'height' => imagesy($image)];
    }
}
