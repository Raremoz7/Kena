<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\BannerImage;
use Illuminate\Console\Command;

class OptimizeBanners extends Command
{
    protected $signature = 'kena:optimize-banners';

    protected $description = 'Recomprime os banners já enviados para WebP leve (o arquivo original fica no disco)';

    public function handle(): int
    {
        $done = 0;

        Event::whereNotNull('banner_image')->each(function (Event $event) use (&$done): void {
            $optimized = BannerImage::optimize($event->banner_image);

            if ($optimized === null) {
                return;
            }

            $this->line("{$event->slug}: {$event->banner_image} → {$optimized}");
            $event->forceFill(['banner_image' => $optimized])->save();
            $done++;
        });

        $this->info("Banners recomprimidos: {$done}");

        return self::SUCCESS;
    }
}
