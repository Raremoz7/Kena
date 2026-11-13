<?php

namespace App\Services\Photos;

use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoGroup;
use App\Models\PhotoPerson;
use Closure;
use GdImage;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Importa para uma galeria as fotos exportadas do Lightroom: turma pela
 * subpasta, pessoas pelo XMP, prévia e miniatura com marca d'água e o rosto
 * de cada pessoa para o filtro.
 *
 * Idempotente: a chave de cada foto é o caminho relativo do original
 * (`original_ref`), então rodar de novo importa só as que ainda não entraram.
 */
final class PhotoImporter
{
    public function __construct(
        private readonly XmpReader $xmp,
        private readonly PhotoRenditions $renditions,
    ) {}

    /**
     * @param  (Closure(SourcePhoto, string): void)|null  $progress  chamado a cada foto com "importada", "existente" ou "falhou"
     */
    public function import(PhotoGallery $gallery, PhotoSource $source, ?Closure $progress = null): ImportResult
    {
        $result = new ImportResult;

        $known = Photo::where('photo_gallery_id', $gallery->id)->pluck('original_ref')->filter()->flip()->all();
        $groups = $gallery->groups()->get()->keyBy('name')->all();
        $people = $gallery->people()->get()->keyBy('name')->all();
        $position = (int) Photo::where('photo_gallery_id', $gallery->id)->max('position');
        $best = [];   // id da pessoa => maior área de rosto já usada (em pixels da miniatura)

        foreach ($source->photos() as $item) {
            if (isset($known[$item->ref])) {
                $result->skipped++;
                $progress?->__invoke($item, 'existente');

                continue;
            }

            try {
                $this->importOne($gallery, $item, $position + 1, $groups, $people, $best, $result);
                $position++;
                $result->imported++;
                $progress?->__invoke($item, 'importada');
            } catch (Throwable $e) {
                $result->failed[$item->ref] = $e->getMessage();
                $progress?->__invoke($item, 'falhou');
            }
        }

        return $result;
    }

    /**
     * @param  array<string, PhotoGroup>  $groups
     * @param  array<string, PhotoPerson>  $people
     * @param  array<int, float>  $best
     */
    private function importOne(
        PhotoGallery $gallery,
        SourcePhoto $item,
        int $position,
        array &$groups,
        array &$people,
        array &$best,
        ImportResult $result,
    ): void {
        $path = $item->localPath();
        $metadata = $this->xmp->read($path);

        $image = $this->load($path, $metadata->orientation);

        try {
            $base = sprintf('fotos/%d/%s', $gallery->id, substr(sha1($item->ref), 0, 16));
            $holder = $gallery->copyrightHolder();
            $preview = $this->renditions->preview($image, "{$base}-preview.webp", $holder);
            $thumb = $this->renditions->thumb($image, "{$base}-thumb.webp", $holder);

            // Turma e pessoas ficam fora da transação: se a foto falhar, sobram
            // registros inofensivos e os caches continuam válidos.
            $group = $item->group !== null
                ? $groups[$item->group] ??= $gallery->groups()->create([
                    'name' => $item->group,
                    'position' => count($groups),
                ])
                : null;

            foreach ($metadata->people as $tagged) {
                if (! isset($people[$tagged->name])) {
                    $people[$tagged->name] = $gallery->people()->create(['name' => $tagged->name]);
                    $result->people++;
                }
                // Antes de gravar a foto: o que já existe no banco vem de importações anteriores.
                $best[$people[$tagged->name]->id] ??= $this->bestStoredArea($people[$tagged->name]);
            }

            DB::transaction(function () use ($gallery, $item, $position, $group, $people, $metadata, $preview, $thumb): void {
                $photo = Photo::create([
                    'photo_gallery_id' => $gallery->id,
                    'photo_group_id' => $group?->id,
                    'filename' => $item->filename,
                    'original_ref' => $item->ref,
                    'preview_path' => $preview['path'],
                    'thumb_path' => $thumb['path'],
                    'width' => $thumb['width'],
                    'height' => $thumb['height'],
                    'position' => $position,
                ]);

                foreach ($metadata->people as $tagged) {
                    $photo->people()->attach($people[$tagged->name]->id, [
                        'region' => $tagged->region !== null ? json_encode($tagged->region) : null,
                    ]);
                }
            });

            foreach ($metadata->people as $tagged) {
                if ($tagged->region !== null) {
                    $this->updateFace($people[$tagged->name], $tagged->region, $image, $thumb, $best);
                }
            }
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * O rosto do filtro é o maior recorte da pessoa. A área é medida em pixels
     * da miniatura, valor que dá para refazer a partir do banco numa
     * reimportação.
     *
     * @param  array{x: float, y: float, w: float, h: float}  $region
     * @param  array{path: string, width: int, height: int}  $thumb
     * @param  array<int, float>  $best
     */
    private function updateFace(PhotoPerson $person, array $region, GdImage $image, array $thumb, array &$best): void
    {
        $area = $region['w'] * $thumb['width'] * $region['h'] * $thumb['height'];

        if ($area <= $best[$person->id] && $person->face_path !== null) {
            return;
        }

        $face = $this->renditions->face(
            $image,
            $region,
            sprintf('fotos/%d/rostos/%d.webp', $person->photo_gallery_id, $person->id),
        );
        $person->update(['face_path' => $face['path']]);
        $best[$person->id] = $area;
    }

    /** Maior área de rosto da pessoa nas fotos já importadas. */
    private function bestStoredArea(PhotoPerson $person): float
    {
        $best = 0.0;

        $rows = DB::table('photo_person')
            ->join('photos', 'photos.id', '=', 'photo_person.photo_id')
            ->where('photo_person.photo_person_id', $person->id)
            ->whereNotNull('photo_person.region')
            ->get(['photo_person.region', 'photos.width', 'photos.height']);

        foreach ($rows as $row) {
            $region = json_decode($row->region, true);
            $best = max($best, $region['w'] * $row->width * $region['h'] * $row->height);
        }

        return $best;
    }

    /**
     * Abre o JPEG já na orientação em que ele é visto. As regiões do MWG são
     * normalizadas sobre a imagem exibida, então o recorte só acerta o rosto
     * se a imagem for girada antes.
     */
    private function load(string $path, int $orientation): GdImage
    {
        if (! is_readable($path)) {
            throw new RuntimeException('Arquivo ilegível.');
        }

        $image = @imagecreatefromjpeg($path);
        if ($image === false) {
            throw new RuntimeException('Não é um JPEG válido.');
        }

        return self::orient($image, $orientation);
    }

    /** Aplica a orientação EXIF (1 a 8) aos pixels, deixando a imagem como é exibida. */
    public static function orient(GdImage $image, int $orientation): GdImage
    {
        // imagerotate gira no sentido anti-horário, por isso o 6 (girar 90° horário) usa -90.
        $rotation = match ($orientation) {
            3 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);
            if ($rotated === false) {
                throw new RuntimeException('Falha ao girar a imagem.');
            }
            $image = $rotated;
        }

        // Espelhamentos: 2 e 4 sozinhos; 5 e 7 depois da rotação.
        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        return $image;
    }
}
