<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Banner dos eventos: toda imagem sai redimensionada (até 1920px de largura) e
 * em WebP, para a vitrine e a página do evento carregarem rápido no celular.
 * O caminho salvo no evento é relativo (/storage/banners/...), sem domínio.
 */
class BannerImage
{
    public const MAX_WIDTH = 1920;

    public const QUALITY = 80;

    private const DIR = 'banners';

    /** Comprime e salva o upload; devolve o caminho público. */
    public static function store(UploadedFile $file): ?string
    {
        $webp = self::compress((string) file_get_contents($file->getRealPath()));

        if ($webp === null) {
            // GD sem suporte ao formato: guarda o original para não perder o upload.
            $path = $file->store(self::DIR, 'public');

            return $path === false ? null : self::publicPath($path);
        }

        return self::put($webp);
    }

    /**
     * Recomprime um banner já salvo. Devolve o novo caminho, ou null quando não
     * há o que fazer (já é WebP leve, arquivo fora do storage ou ilegível).
     */
    public static function optimize(string $banner): ?string
    {
        $relative = self::storagePath($banner);

        if ($relative === null || str_ends_with($relative, '.webp') || ! Storage::disk('public')->exists($relative)) {
            return null;
        }

        $webp = self::compress((string) Storage::disk('public')->get($relative));

        if ($webp === null || strlen($webp) >= Storage::disk('public')->size($relative)) {
            return null;
        }

        return self::put($webp);
    }

    /** Caminho no disco local do banner (para o GD da imagem do ingresso). */
    public static function localFile(?string $banner): ?string
    {
        if ($banner === null) {
            return null;
        }

        $relative = self::storagePath($banner);
        $file = $relative !== null
            ? Storage::disk('public')->path($relative)
            : public_path(ltrim((string) parse_url($banner, PHP_URL_PATH), '/'));

        return is_file($file) ? $file : null;
    }

    /** Bytes WebP redimensionados, ou null se o GD não conseguir ler/gerar. */
    public static function compress(string $bytes): ?string
    {
        if (! function_exists('imagewebp')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if (! $source instanceof GdImage) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $image = $source;

        if ($width > self::MAX_WIDTH) {
            $newHeight = max(1, (int) round($height * self::MAX_WIDTH / $width));
            $image = imagecreatetruecolor(self::MAX_WIDTH, $newHeight);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagecopyresampled($image, $source, 0, 0, 0, 0, self::MAX_WIDTH, $newHeight, $width, $height);
        } elseif (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        ob_start();
        $ok = imagewebp($image, null, self::QUALITY);
        $webp = (string) ob_get_clean();

        return $ok && $webp !== '' ? $webp : null;
    }

    private static function put(string $webp): string
    {
        $path = self::DIR.'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $webp);

        return self::publicPath($path);
    }

    private static function publicPath(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    /** "banners/x.png" a partir de "/storage/banners/x.png" ou da URL completa. */
    private static function storagePath(string $banner): ?string
    {
        $path = (string) parse_url($banner, PHP_URL_PATH);

        return str_starts_with($path, '/storage/') ? substr($path, strlen('/storage/')) : null;
    }
}
