<?php

namespace App\Services\Photos;

use RuntimeException;

/**
 * Grava e lê um pacote XMP dentro de um WebP (contêiner RIFF). O GD gera WebP
 * sem metadados; aqui o arquivo vira o formato estendido (chunk VP8X com a
 * flag de XMP ligada) e o pacote entra num chunk "XMP " no fim.
 */
final class WebpMetadata
{
    private const FLAG_XMP = 0x04;

    /** Pacote XMP de direito autoral: dc:rights, xmpRights:Marked e termos de uso. */
    public static function copyrightPacket(string $holder, int $year): string
    {
        $rights = "© {$year} {$holder}. Todos os direitos reservados. Lei 9.610/98.";
        $terms = 'Uso somente mediante compra. Remover ou alterar esta informação de autoria é infração (Lei 9.610/98, art. 107).';

        $holder = htmlspecialchars($holder, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xpacket begin="'."\u{FEFF}".'" id="W5M0MpCehiHzreSzNTczkc9d"?>'
            .'<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
            .'<rdf:Description rdf:about=""'
            .' xmlns:dc="http://purl.org/dc/elements/1.1/"'
            .' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
            .' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"'
            .' xmpRights:Marked="True"'
            .' photoshop:Credit="'.$holder.'">'
            .'<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">'.htmlspecialchars($rights, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</rdf:li></rdf:Alt></dc:rights>'
            .'<dc:creator><rdf:Seq><rdf:li>'.$holder.'</rdf:li></rdf:Seq></dc:creator>'
            .'<xmpRights:UsageTerms><rdf:Alt><rdf:li xml:lang="x-default">'.htmlspecialchars($terms, ENT_XML1, 'UTF-8').'</rdf:li></rdf:Alt></xmpRights:UsageTerms>'
            .'</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="r"?>';
    }

    /** Devolve o WebP com o pacote XMP embutido (substitui um XMP anterior). */
    public static function embedXmp(string $webp, string $xmp): string
    {
        $chunks = self::chunks($webp);

        $chunks = array_values(array_filter($chunks, fn (array $c): bool => $c[0] !== 'XMP '));

        if ($chunks[0][0] !== 'VP8X') {
            [$width, $height] = self::dimensions($chunks[0]);
            // flags (4 bytes) + largura-1 e altura-1 em 24 bits little-endian.
            $payload = pack('V', 0).substr(pack('V', $width - 1), 0, 3).substr(pack('V', $height - 1), 0, 3);
            array_unshift($chunks, ['VP8X', $payload]);
        }

        // Liga a flag de XMP sem mexer nas outras (alfa, ICC...).
        $flags = unpack('V', substr($chunks[0][1], 0, 4))[1] | self::FLAG_XMP;
        $chunks[0][1] = pack('V', $flags).substr($chunks[0][1], 4);
        $chunks[] = ['XMP ', $xmp];

        $body = '';
        foreach ($chunks as [$id, $data]) {
            $body .= $id.pack('V', strlen($data)).$data.(strlen($data) % 2 === 1 ? "\0" : '');
        }

        return 'RIFF'.pack('V', strlen($body) + 4).'WEBP'.$body;
    }

    /** Lê o pacote XMP de um WebP, ou null se não tiver. */
    public static function readXmp(string $webp): ?string
    {
        foreach (self::chunks($webp) as [$id, $data]) {
            if ($id === 'XMP ') {
                return $data;
            }
        }

        return null;
    }

    /**
     * @return list<array{string, string}> chunks (id, dados) na ordem do arquivo
     */
    private static function chunks(string $webp): array
    {
        if (strlen($webp) < 20 || substr($webp, 0, 4) !== 'RIFF' || substr($webp, 8, 4) !== 'WEBP') {
            throw new RuntimeException('Não é um WebP válido.');
        }

        $chunks = [];
        $pos = 12;
        $end = min(strlen($webp), 8 + unpack('V', substr($webp, 4, 4))[1]);

        while ($pos + 8 <= $end) {
            $id = substr($webp, $pos, 4);
            $size = unpack('V', substr($webp, $pos + 4, 4))[1];
            $chunks[] = [$id, substr($webp, $pos + 8, $size)];
            $pos += 8 + $size + ($size % 2);
        }

        if ($chunks === []) {
            throw new RuntimeException('WebP sem conteúdo.');
        }

        return $chunks;
    }

    /**
     * Largura e altura do primeiro chunk de imagem (VP8 com perda, VP8L sem perda).
     *
     * @param  array{string, string}  $chunk
     * @return array{int, int}
     */
    private static function dimensions(array $chunk): array
    {
        [$id, $data] = $chunk;

        if ($id === 'VP8 ' && strlen($data) >= 10) {
            return [unpack('v', substr($data, 6, 2))[1] & 0x3FFF, unpack('v', substr($data, 8, 2))[1] & 0x3FFF];
        }

        if ($id === 'VP8L' && strlen($data) >= 5) {
            $bits = unpack('V', substr($data, 1, 4))[1];

            return [($bits & 0x3FFF) + 1, (($bits >> 14) & 0x3FFF) + 1];
        }

        throw new RuntimeException('Formato de WebP não suportado.');
    }
}
