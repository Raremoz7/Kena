<?php

namespace App\Services\Photos;

use GdImage;
use RuntimeException;

/**
 * Grava JPGs com os metadados no formato que o Lightroom Classic exporta
 * (regiões MWG, palavras-chave e orientação), para testar o importador sem
 * fotos reais.
 */
final class LightroomFixtureWriter
{
    /**
     * Pacote XMP no formato do Lightroom.
     *
     * @param  array<string, array{x: float, y: float, w: float, h: float}|null>  $people  nome => região (nula = só palavra-chave)
     * @param  list<string>  $keywords  palavras-chave que não são pessoas (ex.: "Palco")
     */
    public static function xmp(array $people, array $keywords = [], int $orientation = 1, bool $personFolder = true): string
    {
        $regions = '';
        foreach ($people as $name => $region) {
            if ($region === null) {
                continue;
            }
            $regions .= sprintf(
                '<rdf:li><rdf:Description mwg-rs:Name="%s" mwg-rs:Type="Face"><mwg-rs:Area stArea:x="%s" stArea:y="%s" stArea:w="%s" stArea:h="%s" stArea:unit="normalized"/></rdf:Description></rdf:li>',
                htmlspecialchars((string) $name, ENT_XML1 | ENT_QUOTES),
                $region['x'], $region['y'], $region['w'], $region['h'],
            );
        }

        $flat = '';
        $hierarchy = '';
        foreach (array_keys($people) as $name) {
            $escaped = htmlspecialchars((string) $name, ENT_XML1 | ENT_QUOTES);
            $flat .= "<rdf:li>{$escaped}</rdf:li>";
            $hierarchy .= '<rdf:li>'.($personFolder ? 'Pessoas|' : '')."{$escaped}</rdf:li>";
        }
        foreach ($keywords as $keyword) {
            $escaped = htmlspecialchars($keyword, ENT_XML1 | ENT_QUOTES);
            $flat .= "<rdf:li>{$escaped}</rdf:li>";
            $hierarchy .= "<rdf:li>{$escaped}</rdf:li>";
        }

        $regionBlock = $regions === '' ? '' : <<<XML
   <mwg-rs:Regions rdf:parseType="Resource">
    <mwg-rs:AppliedToDimensions stDim:w="2000" stDim:h="1333" stDim:unit="pixel"/>
    <mwg-rs:RegionList><rdf:Bag>{$regions}</rdf:Bag></mwg-rs:RegionList>
   </mwg-rs:Regions>
XML;

        $keywordBlock = $flat === '' ? '' : <<<XML
   <dc:subject><rdf:Bag>{$flat}</rdf:Bag></dc:subject>
   <lr:hierarchicalSubject><rdf:Bag>{$hierarchy}</rdf:Bag></lr:hierarchicalSubject>
XML;

        return <<<XML
<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Adobe XMP Core 7.0-c000 1.000000">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:tiff="http://ns.adobe.com/tiff/1.0/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:lr="http://ns.adobe.com/lightroom/1.0/"
    xmlns:mwg-rs="http://www.metadataworkinggroup.com/schemas/regions/"
    xmlns:stDim="http://ns.adobe.com/xap/1.0/sType/Dimensions#"
    xmlns:stArea="http://ns.adobe.com/xmp/sType/Area#"
   tiff:Orientation="{$orientation}">
{$regionBlock}
{$keywordBlock}
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XML;
    }

    /** Insere o XMP (e, se não for 1, a orientação EXIF) num JPEG já codificado. */
    public static function embed(string $jpeg, string $xmp, int $orientation = 1): string
    {
        if (! str_starts_with($jpeg, "\xFF\xD8")) {
            throw new RuntimeException('Não é um JPEG.');
        }

        $segments = '';
        if ($orientation !== 1) {
            $tiff = "MM\0*\0\0\0\x08".pack('n', 1).pack('nnN', 0x0112, 3, 1).pack('n', $orientation)."\0\0".pack('N', 0);
            $segments .= self::app1("Exif\0\0".$tiff);
        }
        $segments .= self::app1("http://ns.adobe.com/xap/1.0/\0".$xmp);

        // Depois do APP0 (JFIF), se houver.
        $at = 2;
        if (substr($jpeg, 2, 2) === "\xFF\xE0") {
            $length = unpack('n', $jpeg, 4);
            if ($length === false) {
                throw new RuntimeException('JPEG inválido.');
            }
            $at = 4 + $length[1];
        }

        return substr($jpeg, 0, $at).$segments.substr($jpeg, $at);
    }

    /**
     * Grava o JPG. A imagem recebida está de pé; com orientação diferente de 1
     * ela é guardada girada, como a câmera faz, e a etiqueta EXIF manda
     * desfazer a rotação. As regiões continuam relativas à imagem de pé.
     *
     * @param  array<string, array{x: float, y: float, w: float, h: float}|null>  $people
     * @param  list<string>  $keywords
     */
    public static function write(string $path, GdImage $upright, array $people, array $keywords = [], int $orientation = 1, bool $personFolder = true): void
    {
        $stored = match ($orientation) {
            6 => imagerotate($upright, 90, 0),   // exibir = girar 90° horário
            8 => imagerotate($upright, -90, 0),
            3 => imagerotate($upright, 180, 0),
            default => $upright,
        };

        if ($stored === false) {
            throw new RuntimeException('Falha ao girar a imagem.');
        }

        ob_start();
        imagejpeg($stored, null, 90);
        $jpeg = (string) ob_get_clean();

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, self::embed($jpeg, self::xmp($people, $keywords, $orientation, $personFolder), $orientation));
    }

    private static function app1(string $payload): string
    {
        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }
}
