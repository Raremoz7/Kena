<?php

namespace App\Services\Photos;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Pasta de JPGs exportados. O nome da primeira subpasta é a turma; arquivo
 * solto na raiz fica sem turma.
 */
final class LocalFolderSource implements PhotoSource
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException("Pasta não encontrada: {$root}");
        }

        $this->root = rtrim(str_replace('\\', '/', $real), '/');
    }

    public function photos(): iterable
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $name = $file->getFilename();
            if (! $file->isFile() || str_starts_with($name, '.') || ! preg_match('/\.jpe?g$/i', $name)) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $files[self::normalize(substr($path, strlen($this->root) + 1))] = $path;
        }

        uksort($files, 'strnatcasecmp');

        foreach ($files as $ref => $path) {
            $parts = explode('/', $ref);

            yield new SourcePhoto(
                ref: $ref,
                filename: end($parts),
                group: count($parts) > 1 ? $parts[0] : null,
                localPath: fn (): string => $path,
            );
        }
    }

    /** Acentos em NFC: o Drive no Mac devolve nomes decompostos (NFD). */
    private static function normalize(string $ref): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($ref, \Normalizer::FORM_C) ?: $ref) : $ref;
    }
}
