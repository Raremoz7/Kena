<?php

namespace App\Support;

use App\Models\Ticket;
use App\Support\Presenters\CatalogPresenter;
use GdImage;
use RuntimeException;

/**
 * Ingresso completo em PNG vertical (capa do evento, dados do lugar, QR), para os
 * e-mails. Desenhado em 2x (640px) para ficar nítido em telas de alta densidade.
 *
 * @phpstan-type Rgb array{int<0, 255>, int<0, 255>, int<0, 255>}
 */
final class TicketImage
{
    private const W = 640;

    private const PAD = 48;

    private const RADIUS = 28;

    private const NOTCH = 22;

    private const COVER_H = 300;

    /** Cores da marca, em [r, g, b]. */
    private const WINE = [0x6E, 0x00, 0x21];

    private const CARD = [0xFF, 0xFF, 0xFF];

    private const CREAM = [0x2A, 0x1F, 0x1A];

    private const MUTED = [0x6B, 0x5F, 0x57];

    private const ACCENT = [0xA8, 0x32, 0x3B];

    private const LINE = [0xE2, 0xD8, 0xCC];

    private const QR_BG = [0xF4, 0xEF, 0xE7];

    /** Bytes PNG do ingresso. */
    public static function png(Ticket $ticket): string
    {
        $ticket->loadMissing('session.event.venue');
        $session = $ticket->session;
        $event = $session->event;
        $venue = $event->venue;

        $title = self::wrap(mb_strtoupper($event->title), self::font('Oswald-Bold'), 46, self::W - 2 * self::PAD, 2);

        // Layout calculado antes de criar a imagem (a altura depende das linhas do título).
        $brandY = self::COVER_H + 52;
        $venueY = $brandY + 30 + count($title) * 54 + 44 + 34;
        $ruleY = $venueY + 36;
        $infoTop = $ruleY + 52;
        $perfY = $infoTop + 2 * 104 + 42 + 52;
        $qrSize = 340;
        $box = $qrSize + 36;
        $boxY = $perfY + 44;
        $h = $boxY + $box + 76 + 44;

        $img = imagecreatetruecolor(self::W, $h);
        imagesavealpha($img, true);
        imagealphablending($img, false);
        imagefill($img, 0, 0, self::color($img, [0, 0, 0], 127));
        imagealphablending($img, true);

        self::roundedRect($img, 0, 0, self::W, $h, self::RADIUS, self::color($img, self::CARD));
        self::cover($img, $event->banner_image);

        // Cabeçalho: marca, título, data e local.
        $y = $brandY;
        self::text($img, 'KENA', 'Oswald-Bold', 18, self::PAD, $y, self::ACCENT, 6);
        $y += 30;
        foreach ($title as $line) {
            $y += 54;
            self::text($img, $line, 'Oswald-Bold', 46, self::PAD, $y, self::CREAM);
        }
        $y += 44;
        self::text($img, CatalogPresenter::sessionLabel($session), 'HankenGrotesk-SemiBold', 22, self::PAD, $y, self::CREAM);
        $y += 34;
        self::text($img, self::fit($venue->name.' — '.$venue->city.'/'.$venue->state, 'HankenGrotesk-Regular', 19), 'HankenGrotesk-Regular', 19, self::PAD, $y, self::MUTED);

        // Dados do lugar, em grade de duas colunas.
        $col2 = self::W / 2 + 8;
        $y = $infoTop;
        imageline($img, self::PAD, $ruleY, self::W - self::PAD, $ruleY, self::color($img, self::LINE));
        self::field($img, 'Setor', $ticket->sector_name, self::PAD, $y);
        self::field($img, 'Lugar', $ticket->seat_code, $col2, $y, big: true);
        $y += 104;
        self::field($img, 'Portas', $session->doors_at !== null ? CatalogPresenter::timeLabel($session->doors_at) : '—', self::PAD, $y);
        self::field($img, 'Início', CatalogPresenter::timeLabel($session->starts_at), $col2, $y);
        $y += 104;
        self::field($img, 'Titular', self::fit($ticket->holder_name, 'HankenGrotesk-SemiBold', 26), self::PAD, $y);

        self::perforation($img, $perfY);

        // Canhoto: QR grande e código.
        $qr = imagecreatefromstring(QrImage::png($ticket->qr_token, $qrSize));
        if (! $qr instanceof GdImage) {
            throw new RuntimeException('Falha ao gerar o QR do ingresso.');
        }
        $bx = (int) ((self::W - $box) / 2);
        $by = $boxY;
        self::roundedRect($img, $bx, $by, $bx + $box, $by + $box, 20, self::color($img, self::QR_BG));
        imagecopyresampled($img, $qr, $bx + 18, $by + 18, 0, 0, $qrSize, $qrSize, imagesx($qr), imagesy($qr));
        self::centered($img, $ticket->code, 'HankenGrotesk-SemiBold', 24, $by + $box + 44, self::CREAM, 4);
        self::centered($img, 'Apresente este QR na entrada', 'HankenGrotesk-Regular', 17, $by + $box + 76, self::MUTED);

        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }

    /** Foto do evento recortada no topo; sem foto, um bloco vinho. */
    private static function cover(GdImage $img, ?string $banner): void
    {
        $path = BannerImage::localFile($banner);
        $photo = $path !== null ? @imagecreatefromstring((string) file_get_contents($path)) : false;

        $layer = imagecreatetruecolor(self::W, self::COVER_H);
        if ($photo instanceof GdImage) {
            $sw = imagesx($photo);
            $sh = (int) round($sw * self::COVER_H / self::W);
            $sy = (int) max(0, (imagesy($photo) - $sh) * 0.6);
            imagecopyresampled($layer, $photo, 0, 0, 0, $sy, self::W, self::COVER_H, $sw, min($sh, imagesy($photo)));
        } else {
            imagefill($layer, 0, 0, self::color($layer, self::WINE));
        }

        // Escurece a base da foto para emendar com o cartão.
        for ($i = 0; $i < 120; $i++) {
            $alpha = max(0, min(127, (int) (127 - 127 * ($i / 120))));
            imageline($layer, 0, self::COVER_H - 120 + $i, self::W, self::COVER_H - 120 + $i, self::color($layer, self::CARD, $alpha));
        }

        // Copia só dentro dos cantos arredondados de cima.
        for ($y = 0; $y < self::COVER_H; $y++) {
            $inset = $y < self::RADIUS ? (int) ceil(self::RADIUS - sqrt(self::RADIUS ** 2 - (self::RADIUS - $y) ** 2)) : 0;
            imagecopy($img, $layer, $inset, $y, $inset, $y, self::W - 2 * $inset, 1);
        }
    }

    private static function field(GdImage $img, string $label, string $value, int|float $x, int $y, bool $big = false): void
    {
        self::text($img, mb_strtoupper($label), 'HankenGrotesk-SemiBold', 15, $x, $y, self::MUTED, 3);
        self::text($img, $value, $big ? 'Oswald-Bold' : 'HankenGrotesk-SemiBold', $big ? 40 : 26, $x, $y + ($big ? 52 : 42), self::CREAM);
    }

    /** Linha picotada com recortes semicirculares nas laterais. */
    private static function perforation(GdImage $img, int $y): void
    {
        $clear = self::color($img, [0, 0, 0], 127);
        imagealphablending($img, false);
        imagefilledellipse($img, 0, $y, self::NOTCH * 2, self::NOTCH * 2, $clear);
        imagefilledellipse($img, self::W, $y, self::NOTCH * 2, self::NOTCH * 2, $clear);
        imagealphablending($img, true);

        $dash = self::color($img, self::LINE);
        for ($x = self::NOTCH + 12; $x < self::W - self::NOTCH - 12; $x += 18) {
            imagefilledrectangle($img, $x, $y - 1, $x + 9, $y + 1, $dash);
        }
    }

    private static function roundedRect(GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r - 1, $y2 - 1, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2 - 1, $y2 - $r - 1, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r - 1, $y1 + $r], [$x1 + $r, $y2 - $r - 1], [$x2 - $r - 1, $y2 - $r - 1]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    /** @param Rgb $rgb */
    private static function text(GdImage $img, string $text, string $font, int $size, int|float $x, int $y, array $rgb, int $tracking = 0): void
    {
        $color = self::color($img, $rgb);
        if ($tracking === 0) {
            imagettftext($img, $size, 0, (int) $x, $y, $color, self::font($font), $text);

            return;
        }
        foreach (mb_str_split($text) as $char) {
            $box = imagettftext($img, $size, 0, (int) $x, $y, $color, self::font($font), $char);
            if ($box === false) {
                throw new RuntimeException('Falha ao desenhar texto no ingresso.');
            }
            $x = $box[2] + $tracking;
        }
    }

    /** @param Rgb $rgb */
    private static function centered(GdImage $img, string $text, string $font, int $size, int $y, array $rgb, int $tracking = 0): void
    {
        $width = self::width($text, $font, $size) + max(0, mb_strlen($text) - 1) * $tracking;
        self::text($img, $text, $font, $size, (self::W - $width) / 2, $y, $rgb, $tracking);
    }

    private static function width(string $text, string $font, int $size): int
    {
        return self::measure(self::font($font), $size, $text);
    }

    /** Corta com reticências o que não cabe na largura útil. */
    private static function fit(string $text, string $font, int $size): string
    {
        $max = self::W - 2 * self::PAD;
        if (self::width($text, $font, $size) <= $max) {
            return $text;
        }
        while (mb_strlen($text) > 1 && self::width($text.'…', $font, $size) > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /**
     * Quebra em até $lines linhas; a última leva reticências se sobrar texto.
     *
     * @return list<string>
     */
    private static function wrap(string $text, string $fontPath, int $size, int $max, int $lines): array
    {
        $out = [];
        $current = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $current === '' ? $word : "$current $word";
            if (self::measure($fontPath, $size, $try) <= $max || $current === '') {
                $current = $try;

                continue;
            }
            $out[] = $current;
            $current = $word;
        }
        $out[] = $current;

        if (count($out) > $lines) {
            $out = array_slice($out, 0, $lines);
            $out[$lines - 1] .= '…';
        }

        return array_values($out);
    }

    private static function measure(string $fontPath, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $fontPath, $text);
        if ($box === false) {
            throw new RuntimeException('Falha ao medir texto do ingresso.');
        }

        return $box[2] - $box[0];
    }

    private static function font(string $name): string
    {
        return resource_path("fonts/{$name}.ttf");
    }

    /**
     * @param  Rgb  $rgb
     * @param  int<0, 127>  $alpha
     */
    private static function color(GdImage $img, array $rgb, int $alpha = 0): int
    {
        $color = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        if ($color === false) {
            throw new RuntimeException('Falha ao alocar cor no ingresso.');
        }

        return $color;
    }
}
