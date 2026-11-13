<?php

namespace App\Services\Photos;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Lê do JPG exportado pelo Lightroom Classic quem aparece na foto.
 *
 * O Lightroom grava as regiões de rosto no pacote XMP (`mwg-rs:RegionInfo`),
 * e também os nomes como palavra-chave (`dc:subject` e
 * `lr:hierarchicalSubject`). Usamos as regiões quando existem; sem região,
 * ficam só as palavras-chave de pessoa, sem recorte.
 *
 * PHP puro: percorre os segmentos do JPEG atrás do APP1 do XMP (e do EXIF, para
 * a orientação), sem depender do exiftool.
 */
final class XmpReader
{
    private const NS = [
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'dc' => 'http://purl.org/dc/elements/1.1/',
        'lr' => 'http://ns.adobe.com/lightroom/1.0/',
        'mwg-rs' => 'http://www.metadataworkinggroup.com/schemas/regions/',
        'stArea' => 'http://ns.adobe.com/xmp/sType/Area#',
        'tiff' => 'http://ns.adobe.com/tiff/1.0/',
    ];

    /** Pastas de palavra-chave que o Lightroom/editores usam para pessoas. */
    private const PERSON_PARENTS = ['pessoas', 'pessoa', 'people', 'person'];

    private const XMP_HEADER = "http://ns.adobe.com/xap/1.0/\0";

    public function read(string $path): PhotoMetadata
    {
        [$xmp, $exif] = $this->segments($path);

        $people = [];
        $xmpOrientation = null;
        if ($xmp !== null) {
            [$people, $xmpOrientation] = $this->parse($xmp);
        }

        $orientation = $exif !== null ? $this->exifOrientation($exif) : null;

        return new PhotoMetadata($people, $orientation ?? $xmpOrientation ?? 1);
    }

    /**
     * Interpreta o texto do pacote XMP.
     *
     * @return array{0: list<TaggedPerson>, 1: int|null} pessoas e a orientação (tiff:Orientation)
     */
    public function parse(string $xmp): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xmp, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [[], null];
        }

        $xpath = new DOMXPath($dom);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        // Regiões primeiro; quem só aparece como palavra-chave de pessoa entra sem recorte.
        $people = $this->unique([...$this->regions($xpath), ...$this->keywordPeople($xpath)]);

        $orientation = null;
        $node = $xpath->query('//@tiff:Orientation | //tiff:Orientation')->item(0);
        if ($node !== null && ctype_digit(trim($node->nodeValue))) {
            $orientation = (int) trim($node->nodeValue);
        }

        return [$people, $orientation];
    }

    /** @return list<TaggedPerson> */
    private function regions(DOMXPath $xpath): array
    {
        $people = [];

        foreach ($xpath->query('//mwg-rs:RegionList/rdf:Bag/rdf:li | //mwg-rs:RegionList/rdf:Seq/rdf:li') as $item) {
            $type = $this->value($xpath, $item, 'mwg-rs:Type');
            if ($type !== null && strcasecmp($type, 'Face') !== 0) {
                continue;
            }

            $name = self::cleanName($this->value($xpath, $item, 'mwg-rs:Name'));
            if ($name === '') {
                continue; // rosto detectado mas sem nome
            }

            $people[] = new TaggedPerson($name, $this->area($xpath, $item));
        }

        return $this->unique($people);
    }

    /** @return array{x: float, y: float, w: float, h: float}|null */
    private function area(DOMXPath $xpath, DOMNode $item): ?array
    {
        $area = [];
        foreach (['x', 'y', 'w', 'h'] as $key) {
            $raw = $this->value($xpath, $item, "stArea:{$key}");
            if ($raw === null || ! is_numeric($raw)) {
                return null;
            }
            $area[$key] = (float) $raw;
        }

        $unit = $this->value($xpath, $item, 'stArea:unit');
        if ($unit !== null && strcasecmp($unit, 'normalized') !== 0) {
            return null;
        }

        if ($area['w'] <= 0 || $area['h'] <= 0
            || $area['x'] < 0 || $area['x'] > 1 || $area['y'] < 0 || $area['y'] > 1) {
            return null;
        }

        return $area;
    }

    /**
     * Valor de uma propriedade XMP dentro do item, seja ela atributo
     * (`mwg-rs:Name="..."`) ou elemento filho (`<mwg-rs:Name>...</mwg-rs:Name>`).
     */
    private function value(DOMXPath $xpath, DOMNode $item, string $property): ?string
    {
        $node = $xpath->query(".//@{$property} | .//{$property}", $item)->item(0);

        if ($node === null) {
            return null;
        }

        return trim($node instanceof DOMElement ? $node->textContent : (string) $node->nodeValue);
    }

    /**
     * Sem região, só vale palavra-chave que seja claramente de pessoa: a que
     * está dentro de uma pasta "Pessoas" na hierarquia de palavras-chave.
     *
     * @return list<TaggedPerson>
     */
    private function keywordPeople(DOMXPath $xpath): array
    {
        $people = [];

        foreach ($xpath->query('//lr:hierarchicalSubject//rdf:li') as $item) {
            $parts = array_map('trim', explode('|', $item->textContent));
            if (count($parts) < 2 || ! in_array(mb_strtolower($parts[0]), self::PERSON_PARENTS, true)) {
                continue;
            }

            $name = self::cleanName(end($parts));
            if ($name !== '') {
                $people[] = new TaggedPerson($name, null);
            }
        }

        return $this->unique($people);
    }

    /**
     * @param  list<TaggedPerson>  $people
     * @return list<TaggedPerson>
     */
    private function unique(array $people): array
    {
        $seen = [];
        foreach ($people as $person) {
            $seen[mb_strtolower($person->name)] ??= $person;
        }

        return array_values($seen);
    }

    /** Nome normalizado: espaços colapsados e acentos em NFC (o Drive no Mac devolve NFD). */
    public static function cleanName(?string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');

        if ($name !== '' && class_exists(\Normalizer::class)) {
            $name = \Normalizer::normalize($name, \Normalizer::FORM_C) ?: $name;
        }

        return $name;
    }

    /**
     * Percorre os segmentos do JPEG até a imagem e devolve o texto do XMP e o
     * bloco EXIF (a partir do cabeçalho TIFF).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function segments(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [null, null];
        }

        $xmp = null;
        $exif = null;

        try {
            if (fread($handle, 2) !== "\xFF\xD8") {
                return [null, null];
            }

            while (! feof($handle)) {
                $byte = fread($handle, 1);
                if ($byte === false || $byte === '') {
                    break;
                }
                if ($byte !== "\xFF") {
                    continue;
                }

                $marker = ord((string) fread($handle, 1));
                while ($marker === 0xFF) { // bytes de preenchimento
                    $marker = ord((string) fread($handle, 1));
                }

                // EOI ou início da imagem: acabaram os metadados.
                if ($marker === 0xD9 || $marker === 0xDA) {
                    break;
                }
                // Marcadores sem tamanho.
                if ($marker === 0x00 || $marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                    continue;
                }

                $length = unpack('n', (string) fread($handle, 2))[1] ?? 0;
                if ($length < 2) {
                    break;
                }

                if ($marker !== 0xE1) {
                    fseek($handle, $length - 2, SEEK_CUR);

                    continue;
                }

                $payload = (string) fread($handle, $length - 2);
                if ($xmp === null && str_starts_with($payload, self::XMP_HEADER)) {
                    $xmp = substr($payload, strlen(self::XMP_HEADER));
                } elseif ($exif === null && str_starts_with($payload, "Exif\0\0")) {
                    $exif = substr($payload, 6);
                }
            }

            // Arquivo fora do padrão: procura o bloco direto nos primeiros bytes.
            if ($xmp === null) {
                rewind($handle);
                $head = (string) fread($handle, 512 * 1024);
                $start = strpos($head, '<x:xmpmeta');
                $end = strpos($head, '</x:xmpmeta>');
                if ($start !== false && $end !== false && $end > $start) {
                    $xmp = substr($head, $start, $end + strlen('</x:xmpmeta>') - $start);
                }
            }
        } finally {
            fclose($handle);
        }

        return [$xmp, $exif];
    }

    /** Orientação (tag 0x0112 do IFD0) a partir do bloco TIFF do EXIF. */
    private function exifOrientation(string $tiff): ?int
    {
        if (strlen($tiff) < 8) {
            return null;
        }

        $little = substr($tiff, 0, 2) === 'II';
        if (! $little && substr($tiff, 0, 2) !== 'MM') {
            return null;
        }

        $short = fn (int $at): int => strlen($tiff) >= $at + 2 ? unpack($little ? 'v' : 'n', $tiff, $at)[1] : 0;
        $long = fn (int $at): int => strlen($tiff) >= $at + 4 ? unpack($little ? 'V' : 'N', $tiff, $at)[1] : 0;

        $ifd = $long(4);
        $count = $short($ifd);

        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($short($entry) === 0x0112) {
                $value = $short($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : null;
            }
        }

        return null;
    }
}
