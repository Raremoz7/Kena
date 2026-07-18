<?php

namespace App\Support;

use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRCodeOutputException;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QROptions;
use chillerlan\Settings\SettingsContainerInterface;

/**
 * Renderiza o QR do ingresso no estilo Kena: módulos com blobs de cantos
 * arredondados (técnica oficial de examples/imageWithRoundedShapes.php do
 * chillerlan/php-qrcode, tag 6.0.1), tinta única para dados e marcadores de
 * canto (sem cor de destaque no "olho" do QR), e o monograma "K" do Kena
 * sobreposto no centro. Não reserva espaço no matrix pro logo — em vez
 * disso deixa a área central em branco no próprio desenho e conta com a
 * correção de erro nível H (configurada em App\Support\QrImage) pra
 * continuar legível.
 */
final class KenaQrRenderer extends QRGdImagePNG
{
    private const INK = [0x3A, 0x2A, 0x24];

    private const CREAM = [0xF4, 0xEF, 0xE7];

    private const LOGO_FRACTION = 0.30;

    private const DEFAULT_LOGO_PATH = 'img/kena-qr-mark.png';

    /** @param SettingsContainerInterface|QROptions|iterable<string, mixed> $options */
    public function __construct(SettingsContainerInterface|QROptions|iterable $options, QRMatrix $matrix)
    {
        if (is_iterable($options)) {
            // @phpstan-ignore argument.type (QROptions aceita qualquer iterable em tempo de execução, a assinatura só documenta array)
            $options = new QROptions($options);
        }

        // habilita o upscale interno da lib (renderiza em 10x e reduz no
        // final) pra dar anti-aliasing nos cantos arredondados em escalas
        // pequenas — precisa ser setado antes do parent::__construct.
        // @phpstan-ignore property.notFound (propriedade dinâmica do QROptions)
        $options->gdImageUseUpscale = true;
        // @phpstan-ignore property.notFound (propriedade dinâmica do QROptions)
        $options->drawCircularModules = true;

        parent::__construct($options, $matrix);
    }

    protected function getDefaultModuleValue(bool $isDark): int
    {
        return $this->prepareModuleValue($isDark ? self::INK : self::CREAM);
    }

    /**
     * [início, fim) em módulos do quadrado central reservado ao logo.
     *
     * @return array{int, int}
     */
    private function logoBoundsInModules(): array
    {
        $count = $this->matrix->moduleCount;
        $size = (int) round($count * self::LOGO_FRACTION);
        $start = (int) floor(($count - $size) / 2);

        return [$start, $start + $size];
    }

    protected function module(int $x, int $y, int $M_TYPE): void
    {
        [$logoStart, $logoEnd] = $this->logoBoundsInModules();

        if ($x >= $logoStart && $x < $logoEnd && $y >= $logoStart && $y < $logoEnd) {
            return;
        }

        $neighbours = $this->matrix->checkNeighbours($x, $y);

        $x1 = $x * $this->scale;
        $y1 = $y * $this->scale;
        $x2 = ($x + 1) * $this->scale;
        $y2 = ($y + 1) * $this->scale;
        $rectsize = (int) ($this->scale / 2);

        $light = $this->getModuleValue($M_TYPE);
        $dark = $this->getModuleValue($M_TYPE | QRMatrix::IS_DARK);

        if ($neighbours & (1 << 7)) {
            imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $light);
        }

        if ($neighbours & (1 << 3)) {
            imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $light);
        }

        if ($neighbours & (1 << 1)) {
            imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $light);
        }

        if ($neighbours & (1 << 5)) {
            imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $light);
            imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $light);
        }

        if (! $this->matrix->check($x, $y)) {
            if (($neighbours & 1) && ($neighbours & (1 << 7)) && ($neighbours & (1 << 1))) {
                imagefilledrectangle($this->image, $x1, $y1, $x1 + $rectsize, $y1 + $rectsize, $dark);
            }

            if (($neighbours & (1 << 1)) && ($neighbours & (1 << 2)) && ($neighbours & (1 << 3))) {
                imagefilledrectangle($this->image, $x2 - $rectsize, $y1, $x2, $y1 + $rectsize, $dark);
            }

            if (($neighbours & (1 << 7)) && ($neighbours & (1 << 6)) && ($neighbours & (1 << 5))) {
                imagefilledrectangle($this->image, $x1, $y2 - $rectsize, $x1 + $rectsize, $y2, $dark);
            }

            if (($neighbours & (1 << 3)) && ($neighbours & (1 << 4)) && ($neighbours & (1 << 5))) {
                imagefilledrectangle($this->image, $x2 - $rectsize, $y2 - $rectsize, $x2, $y2, $dark);
            }
        }

        imagefilledellipse(
            $this->image,
            (int) ($x * $this->scale + $this->scale / 2),
            (int) ($y * $this->scale + $this->scale / 2),
            $this->scale - 1,
            $this->scale - 1,
            $light,
        );
    }

    public function dump(?string $file = null, ?string $logo = null): string
    {
        $logo ??= public_path(self::DEFAULT_LOGO_PATH);

        // @phpstan-ignore property.notFound (propriedade dinâmica do QROptions)
        $this->options->returnResource = true;

        if (! is_file($logo) || ! is_readable($logo)) {
            throw new QRCodeOutputException("logo inválido: {$logo}");
        }

        parent::dump($file);

        $im = imagecreatefrompng($logo);

        if ($im === false) {
            throw new QRCodeOutputException('imagecreatefrompng() error');
        }

        $w = imagesx($im);
        $h = imagesy($im);

        [$logoStart, $logoEnd] = $this->logoBoundsInModules();
        $logoSizePx = ($logoEnd - $logoStart) * $this->scale;
        $logoOffsetPx = $logoStart * $this->scale;

        imagecopyresampled($this->image, $im, $logoOffsetPx, $logoOffsetPx, 0, 0, $logoSizePx, $logoSizePx, $w, $h);

        $imageData = $this->dumpImage();

        $this->saveToFile($imageData, $file);

        // @phpstan-ignore property.notFound (propriedade dinâmica do QROptions)
        if ($this->options->outputBase64) {
            $imageData = $this->toBase64DataURI($imageData);
        }

        return $imageData;
    }
}
