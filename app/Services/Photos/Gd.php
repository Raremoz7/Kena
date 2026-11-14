<?php

namespace App\Services\Photos;

use GdImage;
use RuntimeException;

/**
 * Atalhos para as funções do GD que devolvem `false` em caso de falha, para o
 * resto do código trabalhar sempre com tipos certos.
 */
final class Gd
{
    /** Imagem em branco (preta) true color. */
    public static function canvas(int $width, int $height): GdImage
    {
        if ($width < 1 || $height < 1) {
            throw new RuntimeException("Dimensões inválidas para a imagem: {$width}x{$height}");
        }

        return imagecreatetruecolor($width, $height) ?: throw new RuntimeException('Falha ao criar a imagem.');
    }

    /** Cor opaca; cada canal é limitado a 0..255. */
    public static function color(GdImage $image, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($image, self::channel($red), self::channel($green), self::channel($blue));

        return $color === false ? throw new RuntimeException('Falha ao alocar cor.') : $color;
    }

    /** Cor com transparência; `$alpha` vai de 0 (opaco) a 127 (invisível). */
    public static function colorAlpha(GdImage $image, int $red, int $green, int $blue, int $alpha): int
    {
        $color = imagecolorallocatealpha(
            $image,
            self::channel($red),
            self::channel($green),
            self::channel($blue),
            max(0, min(127, $alpha)),
        );

        return $color === false ? throw new RuntimeException('Falha ao alocar cor.') : $color;
    }

    /**
     * Caixa que envolve o texto (mesmo formato de `imagettfbbox`).
     *
     * @return array<int, int>
     */
    public static function textBox(float $size, string $font, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? throw new RuntimeException('Falha ao medir o texto.') : $box;
    }

    /**
     * @return int<0, 255>
     */
    private static function channel(int $value): int
    {
        return max(0, min(255, $value));
    }
}
