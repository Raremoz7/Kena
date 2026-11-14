<?php

namespace App\Console\Commands;

use App\Services\Photos\DemoStageArtist;
use App\Services\Photos\LightroomFixtureWriter;
use Illuminate\Console\Command;

/**
 * Gera uma pasta de JPGs ilustrados com o XMP no formato do Lightroom, uma
 * subpasta por turma, para testar `fotos:importar` sem as fotos reais.
 */
class MakePhotoFixtures extends Command
{
    protected $signature = 'fotos:fixtures {pasta : Pasta de destino}';

    protected $description = 'Gera JPGs de teste com metadados no formato do Lightroom (somente local)';

    private const GROUPS = [
        'Baby Class' => [[236, 150, 186], [110, 40, 90], ['Alice Moura', 'Helena Costa', 'Laura Batista', 'Cecília Ramos', 'Manuela Dias']],
        'Infantil' => [[150, 160, 236], [40, 52, 120], ['Valentina Rocha', 'Sophia Lima', 'Isabela Nunes', 'Lívia Teixeira', 'Beatriz Alves']],
        'Juvenil' => [[196, 40, 62], [120, 30, 40], ['Júlia Carvalho', 'Lorena Pires', 'Clara Mendes', 'Marina Fonseca', 'Giovanna Souza']],
    ];

    public function handle(DemoStageArtist $artist): int
    {
        if (! app()->isLocal()) {
            $this->error('Este comando só roda no ambiente local.');

            return self::FAILURE;
        }

        $root = rtrim((string) $this->argument('pasta'), '/\\');
        $seed = 5000;
        $index = 0;
        $total = 0;
        /** @var array<string, array{key: string, traits: array{skin: int, hair: int, style: int}, costume: array{0: int, 1: int, 2: int}}> $kids */
        $kids = [];

        foreach (self::GROUPS as $group => [$costume, $stage, $names]) {
            foreach ($names as $name) {
                $kids[$name] = ['key' => $name, 'traits' => DemoStageArtist::traitsFor($index++), 'costume' => $costume];
            }

            for ($i = 1; $i <= 6; $i++) {
                mt_srand(++$seed);
                $cast = collect($names)->shuffle()->take(mt_rand(3, 5))->map(fn ($n) => $kids[$n])->all();
                $shot = $artist->stage($cast, $costume, $stage, $seed);

                // Variações do que o Lightroom exporta de verdade.
                $orientation = $i === 2 ? 6 : 1;                // câmera na vertical
                $named = $i === 5 ? [] : $shot['faces'];        // sem rosto marcado na foto
                $mode = $i === 4 ? 'keywords' : 'regions';      // só palavra-chave, sem região

                $people = $mode === 'keywords'
                    ? array_fill_keys(array_keys($shot['faces']), null)
                    : $named;

                LightroomFixtureWriter::write(
                    sprintf('%s/%s/%s_%04d.jpg', $root, $group, 'PALCO', ++$total),
                    $shot['image'], $people, ['Palco'], $orientation,
                );
                imagedestroy($shot['image']);
            }

            foreach ($names as $name) {
                $shot = $artist->studio($kids[$name], $costume, ++$seed);
                LightroomFixtureWriter::write(
                    sprintf('%s/Estúdio/%s_%04d.jpg', $root, 'ESTUDIO', ++$total),
                    $shot['image'], $shot['faces'], ['Estúdio'],
                );
                imagedestroy($shot['image']);
            }
        }

        $this->info("{$total} fotos de teste em {$root}");

        return self::SUCCESS;
    }
}
