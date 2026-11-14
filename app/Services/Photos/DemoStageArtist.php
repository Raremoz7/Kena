<?php

namespace App\Services\Photos;

use GdImage;

/**
 * Desenha fotos ilustradas de palco e estúdio para testar a galeria sem as
 * fotos reais. Devolve, junto com a imagem, a área de cada rosto no mesmo
 * padrão (MWG) que o Lightroom grava no XMP, para o resto do fluxo ser igual.
 *
 * Só serve para demonstração local.
 */
final class DemoStageArtist
{
    private const SKIN = [[247, 214, 190], [233, 190, 160], [198, 146, 110], [160, 108, 76], [118, 78, 54], [240, 200, 175]];

    private const HAIR = [[40, 26, 20], [88, 56, 34], [150, 104, 60], [214, 170, 100], [20, 16, 16], [120, 60, 30]];

    /**
     * Foto de palco com várias crianças.
     *
     * @param  array<array-key, array{key: string, traits: array{skin: int, hair: int, style: int}}>  $kids
     * @param  array{0: int, 1: int, 2: int}  $costume
     * @param  array{0: int, 1: int, 2: int}  $stage
     * @return array{image: GdImage, faces: array<string, array{x: float, y: float, w: float, h: float}>}
     */
    public function stage(array $kids, array $costume, array $stage, int $seed): array
    {
        mt_srand($seed);
        $w = 2000;
        $h = 1333;
        $img = Gd::canvas($w, $h);

        $this->verticalGradient($img, [(int) ($stage[0] * 0.55), (int) ($stage[1] * 0.55), (int) ($stage[2] * 0.55)], [8, 6, 10]);
        $floorY = (int) ($h * 0.8);

        // Spot de luz quente, em camadas para dar o degradê.
        $spotX = mt_rand((int) ($w * 0.35), (int) ($w * 0.65));
        for ($i = 26; $i >= 1; $i--) {
            $c = Gd::colorAlpha($img, 255, 236, 200, 118);
            imagefilledellipse($img, $spotX, (int) ($h * 0.55), $i * 62, $i * 40, $c);
        }

        // Piso de madeira escura com reflexo do spot.
        $this->rectGradient($img, 0, $floorY, $w, $h, [58, 38, 30], [20, 14, 12]);
        $glow = Gd::colorAlpha($img, 255, 230, 190, 100);
        imagefilledellipse($img, $spotX, $floorY + 40, 1100, 120, $glow);

        // Cortinas laterais.
        $curtain = Gd::color($img, (int) ($stage[0] * 0.8), (int) ($stage[1] * 0.25), (int) ($stage[2] * 0.3));
        $fold = Gd::colorAlpha($img, 0, 0, 0, 80);
        foreach ([[0, 150], [$w - 150, $w]] as [$x1, $x2]) {
            imagefilledrectangle($img, $x1, 0, $x2, $h, $curtain);
            for ($x = $x1 + 18; $x < $x2; $x += 36) {
                imagefilledrectangle($img, $x, 0, $x + 8, $h, $fold);
            }
        }

        // Distribui as crianças pela largura, as de trás menores.
        $n = count($kids);
        $faces = [];
        $placed = [];
        foreach (array_values($kids) as $i => $kid) {
            $depth = mt_rand(0, 100) / 100;
            $placed[] = [
                'kid' => $kid,
                'x' => (int) (260 + ($w - 520) * (($i + 0.5) / $n)) + mt_rand(-50, 50),
                'y' => (int) ($floorY + 70 - $depth * 120),
                's' => 1.55 - $depth * 0.35,
            ];
        }
        usort($placed, fn ($a, $b) => $a['y'] <=> $b['y']);

        foreach ($placed as $p) {
            $head = $this->dancer($img, $p['x'], $p['y'], $p['s'], $p['kid']['traits'], $costume, mt_rand(0, 2));
            $faces[$p['kid']['key']] = $this->region($head, $w, $h);
        }

        return ['image' => $img, 'faces' => $faces];
    }

    /**
     * Retrato de estúdio: uma criança, fundo infinito claro.
     *
     * @param  array{key: string, traits: array{skin: int, hair: int, style: int}}  $kid
     * @param  array{0: int, 1: int, 2: int}  $costume
     * @return array{image: GdImage, faces: array<string, array{x: float, y: float, w: float, h: float}>}
     */
    public function studio(array $kid, array $costume, int $seed): array
    {
        mt_srand($seed);
        $w = 1400;
        $h = 1750;
        $img = Gd::canvas($w, $h);

        $tone = mt_rand(0, 1) ? [[236, 230, 224], [196, 186, 178]] : [[214, 222, 230], [160, 172, 186]];
        $this->verticalGradient($img, $tone[0], $tone[1]);

        // Vinheta suave nas bordas.
        for ($i = 0; $i < 14; $i++) {
            $c = Gd::colorAlpha($img, 0, 0, 0, 124);
            imagerectangle($img, $i * 4, $i * 4, $w - 1 - $i * 4, $h - 1 - $i * 4, $c);
        }

        $shadow = Gd::colorAlpha($img, 0, 0, 0, 105);
        imagefilledellipse($img, (int) ($w / 2), $h - 150, 760, 90, $shadow);

        $head = $this->dancer($img, (int) ($w / 2) + mt_rand(-60, 60), $h - 150, 3.6, $kid['traits'], $costume, mt_rand(0, 2));

        return ['image' => $img, 'faces' => [$kid['key'] => $this->region($head, $w, $h)]];
    }

    /**
     * Traços fixos de uma criança, para o rosto ser o mesmo em todas as fotos.
     *
     * @return array{skin: int, hair: int, style: int}
     */
    public static function traitsFor(int $index): array
    {
        // A cada volta pelos tons de pele muda cabelo e penteado: nenhuma combinação se repete.
        $round = intdiv($index, count(self::SKIN));

        return [
            'skin' => $index % count(self::SKIN),
            'hair' => ($index + 2 * $round + 1) % count(self::HAIR),
            'style' => ($index + $round) % 3,
        ];
    }

    /**
     * Desenha uma bailarina com os pés em ($x, $floor) e devolve o centro e o
     * raio da cabeça.
     *
     * @param  array{skin: int, hair: int, style: int}  $traits
     * @param  array{0: int, 1: int, 2: int}  $costume
     * @return array{x: int, y: int, r: int}
     */
    private function dancer(GdImage $img, int $x, int $floor, float $s, array $traits, array $costume, int $pose): array
    {
        $px = fn (float $v): int => (int) round($v * $s);
        $skin = self::SKIN[$traits['skin']];
        $hair = self::HAIR[$traits['hair']];

        $skinC = Gd::color($img, ...$skin);
        $skinDark = Gd::color($img, (int) ($skin[0] * 0.86), (int) ($skin[1] * 0.84), (int) ($skin[2] * 0.84));
        $hairC = Gd::color($img, ...$hair);
        $costumeC = Gd::color($img, ...$costume);
        $costumeLight = Gd::color($img, min(255, $costume[0] + 40), min(255, $costume[1] + 40), min(255, $costume[2] + 40));
        $tights = Gd::color($img, 240, 214, 214);
        $ink = Gd::color($img, 44, 30, 30);
        $cheek = Gd::colorAlpha($img, 236, 120, 120, 92);

        // Pernas e sapatilhas.
        imagefilledrectangle($img, $x - $px(16), $floor - $px(112), $x - $px(5), $floor - $px(6), $tights);
        imagefilledrectangle($img, $x + $px(5), $floor - $px(112), $x + $px(16), $floor - $px(6), $tights);
        imagefilledellipse($img, $x - $px(12), $floor - $px(4), $px(20), $px(10), $costumeC);
        imagefilledellipse($img, $x + $px(12), $floor - $px(4), $px(20), $px(10), $costumeC);

        // Braços (atrás do corpo).
        $shoulderY = $floor - $px(196);
        $hands = match ($pose) {
            0 => [[-74, -270], [74, -270]],   // braços para cima
            1 => [[-92, -198], [92, -206]],   // abertos
            default => [[-36, -292], [36, -292]], // en haut, em arco
        };
        imagesetthickness($img, max(2, $px(11)));
        foreach ([-1, 1] as $k => $side) {
            imageline($img, $x + $side * $px(20), $shoulderY, $x + $px($hands[$k][0]), $floor + $px($hands[$k][1]), $skinDark);
        }
        imagesetthickness($img, 1);

        // Tutu em camadas e corpete.
        imagefilledellipse($img, $x, $floor - $px(118), $px(168), $px(52), $costumeLight);
        imagefilledellipse($img, $x, $floor - $px(124), $px(150), $px(40), $costumeC);
        imagefilledpolygon($img, [
            $x - $px(27), $floor - $px(126),
            $x + $px(27), $floor - $px(126),
            $x + $px(21), $floor - $px(200),
            $x - $px(21), $floor - $px(200),
        ], $costumeC);

        // Pescoço e cabeça.
        imagefilledrectangle($img, $x - $px(7), $floor - $px(216), $x + $px(7), $floor - $px(196), $skinDark);
        $hy = $floor - $px(246);
        $r = $px(34);

        // Cabelo atrás.
        imagefilledellipse($img, $x, $hy - $px(4), $px(78), $px(76), $hairC);
        if ($traits['style'] === 0) {
            imagefilledellipse($img, $x, $hy - $px(42), $px(32), $px(28), $hairC); // coque
        } elseif ($traits['style'] === 1) {
            imagefilledellipse($img, $x - $px(38), $hy + $px(4), $px(24), $px(48), $hairC); // maria-chiquinha
            imagefilledellipse($img, $x + $px(38), $hy + $px(4), $px(24), $px(48), $hairC);
        } else {
            imagefilledellipse($img, $x, $hy + $px(20), $px(86), $px(70), $hairC); // solto
        }

        imagefilledellipse($img, $x, $hy + $px(4), $px(62), $px(66), $skinC);
        // Franja.
        imagefilledarc($img, $x, $hy - $px(10), $px(66), $px(44), 180, 360, $hairC, IMG_ARC_PIE);

        // Olhos, bochechas e sorriso.
        imagefilledellipse($img, $x - $px(12), $hy + $px(6), max(2, $px(6)), max(3, $px(8)), $ink);
        imagefilledellipse($img, $x + $px(12), $hy + $px(6), max(2, $px(6)), max(3, $px(8)), $ink);
        imagefilledellipse($img, $x - $px(19), $hy + $px(17), $px(12), $px(7), $cheek);
        imagefilledellipse($img, $x + $px(19), $hy + $px(17), $px(12), $px(7), $cheek);
        imagesetthickness($img, max(1, $px(2.4)));
        imagearc($img, $x, $hy + $px(16), $px(18), $px(12), 20, 160, $ink);
        imagesetthickness($img, 1);

        return ['x' => $x, 'y' => $hy, 'r' => $r];
    }

    /**
     * @param  array{x: int, y: int, r: int}  $head
     * @return array{x: float, y: float, w: float, h: float}
     */
    private function region(array $head, int $w, int $h): array
    {
        return [
            'x' => round($head['x'] / $w, 4),
            'y' => round(($head['y'] + $head['r'] * 0.05) / $h, 4),
            'w' => round($head['r'] * 2.5 / $w, 4),
            'h' => round($head['r'] * 2.6 / $h, 4),
        ];
    }

    /**
     * @param  array<int, int>  $from
     * @param  array<int, int>  $to
     */
    private function verticalGradient(GdImage $img, array $from, array $to): void
    {
        $this->rectGradient($img, 0, 0, imagesx($img), imagesy($img), $from, $to);
    }

    /**
     * @param  array<int, int>  $from
     * @param  array<int, int>  $to
     */
    private function rectGradient(GdImage $img, int $x1, int $y1, int $x2, int $y2, array $from, array $to): void
    {
        $span = max(1, $y2 - $y1);
        for ($y = $y1; $y < $y2; $y++) {
            $t = ($y - $y1) / $span;
            $c = Gd::color(
                $img,
                (int) ($from[0] + ($to[0] - $from[0]) * $t),
                (int) ($from[1] + ($to[1] - $from[1]) * $t),
                (int) ($from[2] + ($to[2] - $from[2]) * $t),
            );
            imageline($img, $x1, $y, $x2, $y, $c);
        }
    }
}
