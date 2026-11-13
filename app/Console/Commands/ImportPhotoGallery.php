<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\PhotoGallery;
use App\Services\Photos\LocalFolderSource;
use App\Services\Photos\PhotoImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Importa para a galeria de um evento as fotos exportadas do Lightroom.
 * A subpasta de cada foto vira a turma; os nomes e rostos vêm do XMP.
 * Pode ser repetido à vontade: só entram as fotos novas.
 */
class ImportPhotoGallery extends Command
{
    protected $signature = 'fotos:importar
        {slug : Slug do evento dono da galeria}
        {pasta : Pasta com os JPGs exportados (uma subpasta por turma)}
        {--publicar : Publica a galeria (se for criada agora, ela nasce em rascunho)}';

    protected $description = 'Importa as fotos exportadas do Lightroom para a galeria do evento';

    public function handle(PhotoImporter $importer): int
    {
        // Originais em alta ocupam bastante memória ao decodificar.
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && (int) $limit < 1024 && ! str_contains((string) $limit, 'G')) {
            ini_set('memory_limit', '1G');
        }

        $event = Event::where('slug', $this->argument('slug'))->first();
        if ($event === null) {
            $this->error("Evento não encontrado: {$this->argument('slug')}");

            return self::FAILURE;
        }

        try {
            $source = new LocalFolderSource((string) $this->argument('pasta'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $gallery = PhotoGallery::firstOrCreate(['event_id' => $event->id], [
            'status' => 'draft',
            'description' => 'Fotos do espetáculo, já editadas. Encontre sua bailarina pelo rosto ou pela turma.',
            'price_tiers' => [
                ['min_qty' => 1, 'unit_price_cents' => 3500],
                ['min_qty' => 10, 'unit_price_cents' => 2000],
            ],
        ]);
        if ($gallery->wasRecentlyCreated) {
            $this->line('Galeria criada em rascunho (R$ 35 cada, R$ 20 cada a partir de 10).');
        }

        $verbose = $this->output->isVerbose();
        $result = $importer->import($gallery, $source, function ($item, string $status) use ($verbose): void {
            if ($verbose || $status === 'falhou') {
                $this->line(sprintf('  %-9s %s', $status, $item->ref));
            }
        });

        if ($this->option('publicar') && $gallery->status !== 'published') {
            $gallery->update(['status' => 'published', 'published_at' => now()]);
            $this->line('Galeria publicada.');
        }

        foreach ($result->failed as $ref => $reason) {
            $this->warn("Falhou: {$ref} ({$reason})");
        }

        $this->info(sprintf(
            '%d importadas, %d já existiam, %d falharam. Pessoas novas: %d. Galeria: %d fotos, %d turmas, %d pessoas.',
            $result->imported,
            $result->skipped,
            count($result->failed),
            $result->people,
            $gallery->photos()->count(),
            $gallery->groups()->count(),
            $gallery->people()->count(),
        ));
        $this->line("Abra /fotos/{$event->slug}");

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }
}
