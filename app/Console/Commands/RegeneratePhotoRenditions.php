<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Photos\PhotoRenditionsRegenerator;
use Illuminate\Console\Command;

/**
 * Refaz prévias e miniaturas de uma galeria a partir dos originais. Use depois
 * de trocar o titular do direito autoral (o painel também dispara isso).
 */
class RegeneratePhotoRenditions extends Command
{
    protected $signature = 'fotos:regerar {slug : Slug do evento dono da galeria}';

    protected $description = 'Refaz prévias e miniaturas (marca d\'água e XMP) a partir dos originais';

    public function handle(PhotoRenditionsRegenerator $regenerator): int
    {
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && (int) $limit < 1024 && ! str_contains((string) $limit, 'G')) {
            ini_set('memory_limit', '1G');
        }

        $event = Event::where('slug', $this->argument('slug'))->first();
        $gallery = $event?->photoGallery;
        if ($gallery === null) {
            $this->error("Galeria não encontrada para: {$this->argument('slug')}");

            return self::FAILURE;
        }

        $this->line("Titular do direito autoral: {$gallery->copyrightHolder()}");

        $verbose = $this->output->isVerbose();
        $result = $regenerator->regenerate($gallery, function ($photo, string $status) use ($verbose): void {
            if ($verbose || $status !== 'regerada') {
                $this->line(sprintf('  %-12s %s', $status, $photo->filename));
            }
        });

        $this->info("{$result['done']} regerada(s), {$result['missing']} sem original, ".count($result['failed']).' com falha.');
        if ($result['missing'] > 0) {
            $this->warn('Fotos sem original (ex.: demonstração) não podem ser regeradas e continuam com o aviso antigo.');
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
