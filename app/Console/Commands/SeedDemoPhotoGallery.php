<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Services\Photos\DemoStageArtist;
use App\Services\Photos\PhotoRenditions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Monta uma galeria de demonstração para um evento, com fotos ilustradas,
 * turmas e rostos marcados. Só para desenvolvimento local, enquanto as fotos
 * reais (Lightroom → Drive) não chegam.
 */
class SeedDemoPhotoGallery extends Command
{
    protected $signature = 'fotos:demo {slug=o-quebra-nozes : Evento que recebe a galeria}';

    protected $description = 'Cria uma galeria de fotos de demonstração (somente local)';

    /** Turmas da demonstração: nome, cor do figurino, cor do palco e crianças. */
    private const GROUPS = [
        'Baby Class' => [[236, 150, 186], [110, 40, 90], ['Alice Moura', 'Helena Costa', 'Laura Batista', 'Cecília Ramos', 'Manuela Dias']],
        'Infantil' => [[150, 160, 236], [40, 52, 120], ['Valentina Rocha', 'Sophia Lima', 'Isabela Nunes', 'Lívia Teixeira', 'Beatriz Alves']],
        'Juvenil' => [[196, 40, 62], [120, 30, 40], ['Júlia Carvalho', 'Lorena Pires', 'Clara Mendes', 'Marina Fonseca', 'Giovanna Souza']],
    ];

    private const STAGE_PER_GROUP = 22;

    private const FINALE = 8;

    /** Parte dos rostos fica sem marcação, como acontece de verdade no palco. */
    private const UNTAGGED_RATE = 12;

    public function handle(DemoStageArtist $artist, PhotoRenditions $renditions): int
    {
        if (! app()->isLocal()) {
            $this->error('Este comando só roda no ambiente local.');

            return self::FAILURE;
        }

        $event = Event::where('slug', $this->argument('slug'))->firstOrFail();

        PhotoGallery::where('event_id', $event->id)->get()->each(function (PhotoGallery $old): void {
            Storage::disk(config('kena.photos.disk'))->deleteDirectory("fotos/{$old->id}");
            $old->delete();
        });

        $gallery = PhotoGallery::create([
            'event_id' => $event->id,
            'status' => 'published',
            'description' => 'Fotos do espetáculo e do estúdio, já editadas. Encontre sua bailarina pelo rosto ou pela turma.',
            'price_tiers' => [
                ['min_qty' => 1, 'unit_price_cents' => 3500],
                ['min_qty' => 10, 'unit_price_cents' => 2000],
            ],
            'published_at' => now(),
        ]);

        $dir = "fotos/{$gallery->id}";
        $position = 0;
        $kidIndex = 0;
        $kids = [];          // key => [traits, costume, group]
        $people = [];        // key => PhotoPerson
        $bestFace = [];      // key => área do maior rosto já recortado

        $groups = [];
        foreach (self::GROUPS as $name => [$costume, $stage, $names]) {
            $groups[$name] = $gallery->groups()->create(['name' => $name, 'position' => count($groups)]);
            foreach ($names as $kidName) {
                $key = $kidName;
                $kids[$key] = ['key' => $key, 'traits' => DemoStageArtist::traitsFor($kidIndex++), 'costume' => $costume, 'group' => $name];
                $people[$key] = $gallery->people()->create(['name' => $kidName]);
            }
        }
        $finale = $gallery->groups()->create(['name' => 'Encerramento', 'position' => count($groups)]);
        $studio = $gallery->groups()->create(['name' => 'Estúdio', 'position' => count($groups) + 1]);

        $save = function (array $shot, $group, string $label) use ($renditions, $gallery, $dir, &$position, $people, &$bestFace): void {
            $position++;
            $base = sprintf('%s/%04d', $dir, $position);
            $preview = $renditions->preview($shot['image'], "{$base}-preview.webp");
            $thumb = $renditions->thumb($shot['image'], "{$base}-thumb.webp");

            $photo = Photo::create([
                'photo_gallery_id' => $gallery->id,
                'photo_group_id' => $group->id,
                'filename' => sprintf('%s_%04d.jpg', $label, $position),
                'original_ref' => null,
                'preview_path' => $preview['path'],
                'thumb_path' => $thumb['path'],
                'width' => $thumb['width'],
                'height' => $thumb['height'],
                'position' => $position,
            ]);

            foreach ($shot['faces'] as $key => $region) {
                if (mt_rand(1, 100) <= self::UNTAGGED_RATE) {
                    continue;
                }
                $photo->people()->attach($people[$key]->id, ['region' => json_encode($region)]);

                // A miniatura do filtro vem do maior rosto daquela pessoa.
                $area = $region['w'] * imagesx($shot['image']) * $region['h'] * imagesy($shot['image']);
                if ($area > ($bestFace[$key] ?? 0)) {
                    $bestFace[$key] = $area;
                    $face = $renditions->face($shot['image'], $region, "{$dir}/rostos/{$people[$key]->id}.webp");
                    $people[$key]->update(['face_path' => $face['path']]);
                }
            }

            imagedestroy($shot['image']);
        };

        $bar = $this->output->createProgressBar(count(self::GROUPS) * self::STAGE_PER_GROUP + self::FINALE + count($kids) * 2);
        $seed = 1000;

        foreach (self::GROUPS as $name => [$costume, $stage, $names]) {
            for ($i = 0; $i < self::STAGE_PER_GROUP; $i++) {
                mt_srand(++$seed);
                $cast = collect($names)->shuffle()->take(mt_rand(3, 5))->map(fn ($n) => $kids[$n])->all();
                $save($artist->stage($cast, $costume, $stage, $seed), $groups[$name], 'PALCO');
                $bar->advance();
            }
        }

        for ($i = 0; $i < self::FINALE; $i++) {
            mt_srand(++$seed);
            $cast = collect($kids)->shuffle()->take(6)->all();
            $save($artist->stage($cast, [232, 196, 120], [90, 60, 30], $seed), $finale, 'FINAL');
            $bar->advance();
        }

        foreach ($kids as $kid) {
            for ($i = 0; $i < 2; $i++) {
                $save($artist->studio($kid, $kid['costume'], ++$seed), $studio, 'ESTUDIO');
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Galeria criada: {$position} fotos, ".count($people)." pessoas. Abra /fotos/{$event->slug}");
        $this->info("Código de acesso: {$gallery->access_code}");

        return self::SUCCESS;
    }
}
