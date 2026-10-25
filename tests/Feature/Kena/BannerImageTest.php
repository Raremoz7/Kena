<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\Venue;
use App\Support\BannerImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_is_resized_and_saved_as_webp_with_relative_path(): void
    {
        Storage::fake('public');

        $path = BannerImage::store(UploadedFile::fake()->image('banner.png', 3000, 1200));

        $this->assertNotNull($path);
        $this->assertStringStartsWith('/storage/banners/', $path);
        $this->assertStringEndsWith('.webp', $path);

        $size = getimagesizefromstring(Storage::disk('public')->get(substr($path, strlen('/storage/'))));
        $this->assertSame(BannerImage::MAX_WIDTH, $size[0]);
        $this->assertSame(768, $size[1]);
        $this->assertSame('image/webp', $size['mime']);
        $this->assertNotNull(BannerImage::localFile($path));
    }

    public function test_small_upload_keeps_size_but_becomes_webp(): void
    {
        Storage::fake('public');

        $path = BannerImage::store(UploadedFile::fake()->image('banner.jpg', 800, 400));

        $size = getimagesizefromstring(Storage::disk('public')->get(substr((string) $path, strlen('/storage/'))));
        $this->assertSame([800, 400, 'image/webp'], [$size[0], $size[1], $size['mime']]);
    }

    public function test_command_recompresses_existing_banner_given_as_full_url(): void
    {
        Storage::fake('public');

        // Banner antigo: PNG pesado, salvo com a URL absoluta (como em produção).
        $png = UploadedFile::fake()->image('old.png', 2400, 1000)->store('banners', 'public');
        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        $event = Event::create([
            'venue_id' => $venue->id,
            'slug' => 'evento',
            'title' => 'Evento',
            'kicker' => 'Show',
            'description' => 'Descrição.',
            'status' => 'on_sale',
            'banner_from' => '#4f1a1b',
            'banner_to' => '#0d0806',
            'banner_image' => 'https://kena.example/storage/'.$png,
        ]);

        $this->artisan('kena:optimize-banners')->assertSuccessful();

        $event->refresh();
        $this->assertStringStartsWith('/storage/banners/', $event->banner_image);
        $this->assertStringEndsWith('.webp', $event->banner_image);
        // O original continua no disco (dá para voltar se precisar).
        Storage::disk('public')->assertExists($png);

        // Rodar de novo não mexe no que já está em WebP.
        $this->artisan('kena:optimize-banners')->assertSuccessful();
        $this->assertSame($event->banner_image, $event->fresh()->banner_image);
    }
}
