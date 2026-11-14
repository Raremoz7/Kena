<?php

namespace App\Services\Photos\Drive;

use App\Services\Photos\PhotoSource;
use App\Services\Photos\SourcePhoto;
use InvalidArgumentException;

/**
 * Pasta do evento no Google Drive: o nome da primeira subpasta é a turma
 * (como na pasta local), e arquivo solto na raiz fica sem turma. O arquivo
 * só é baixado quando o importador pede o caminho local, então fotos que já
 * existem na galeria não custam download.
 *
 * `ref` = `drive:<id do arquivo>`: estável mesmo se o editor renomear ou
 * mover o arquivo, e não colide com os caminhos relativos da origem local.
 */
final class DriveFolderSource implements PhotoSource
{
    public const REF_PREFIX = 'drive:';

    private readonly string $folderId;

    public function __construct(private readonly DriveClient $drive, string $folder)
    {
        $id = self::extractId($folder);
        if ($id === null) {
            throw new InvalidArgumentException('Não entendi o link da pasta do Drive. Cole o link da pasta (drive.google.com/drive/folders/...).');
        }

        $this->folderId = $id;
    }

    /** Aceita o link completo da pasta (com /u/0/, ?usp=sharing, etc.) ou o id puro. */
    public static function extractId(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $input, $m)
            || preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $input, $m)) {
            return $m[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{15,}$/', $input) === 1 ? $input : null;
    }

    /** O texto parece um link ou id do Drive (e não um caminho local)? */
    public static function looksLikeDrive(string $input): bool
    {
        return str_contains($input, 'drive.google.com') || preg_match('/^[A-Za-z0-9_-]{25,}$/', trim($input)) === 1;
    }

    public function photos(): iterable
    {
        $this->drive->folder($this->folderId);

        $found = [];
        $this->collect($this->folderId, null, $found);

        usort($found, fn (array $a, array $b): int => strnatcasecmp($a['sort'], $b['sort']) ?: strcmp($a['id'], $b['id']));

        foreach ($found as $file) {
            /** @var string|null $path */
            $path = null;

            yield new SourcePhoto(
                ref: self::REF_PREFIX.$file['id'],
                filename: $file['name'],
                group: $file['group'],
                localPath: function () use ($file, &$path): string {
                    $path = tempnam(sys_get_temp_dir(), 'kena-drive-');
                    $this->drive->download($file['id'], $path);

                    return $path;
                },
                release: function () use (&$path): void {
                    if ($path !== null && is_file($path)) {
                        @unlink($path);
                    }
                    $path = null;
                },
            );
        }
    }

    /**
     * Desce as subpastas. O grupo é sempre a subpasta de primeiro nível,
     * como na origem local.
     *
     * @param  list<array{id: string, name: string, group: ?string, sort: string}>  $found
     */
    private function collect(string $folderId, ?string $group, array &$found): void
    {
        foreach ($this->drive->children($folderId) as $item) {
            if ($item['mimeType'] === DriveClient::FOLDER_MIME) {
                if (! str_starts_with($item['name'], '.')) {
                    $this->collect($item['id'], $group ?? self::normalize($item['name']), $found);
                }

                continue;
            }

            $isJpeg = $item['mimeType'] === 'image/jpeg' || preg_match('/\.jpe?g$/i', $item['name']) === 1;
            if (! $isJpeg || str_starts_with($item['name'], '.')) {
                continue;
            }

            $found[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'group' => $group,
                'sort' => ($group !== null ? $group.'/' : '').$item['name'],
            ];
        }
    }

    /** Acentos em NFC: o Drive devolve nomes decompostos quando a pasta veio de um Mac. */
    private static function normalize(string $name): string
    {
        return class_exists(\Normalizer::class) ? (\Normalizer::normalize($name, \Normalizer::FORM_C) ?: $name) : $name;
    }
}
