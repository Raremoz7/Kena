<?php

namespace App\Support;

/**
 * Linhas de CSV seguras para abrir no Excel/LibreOffice.
 *
 * Nome de comprador é texto livre: "=HYPERLINK(...)" ou "=cmd|..." viraria
 * fórmula na planilha do organizador. Prefixar com apóstrofo faz a célula ser
 * lida como texto (o apóstrofo não aparece na célula).
 */
final class Csv
{
    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $fields
     */
    public static function put($handle, array $fields, string $separator = ','): void
    {
        fputcsv($handle, array_map(self::cell(...), $fields), $separator);
    }

    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
