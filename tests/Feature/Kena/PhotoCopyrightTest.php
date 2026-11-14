<?php

namespace Tests\Feature\Kena;

use App\Jobs\RegeneratePhotoGalleryRenditions;
use App\Models\Event;
use App\Models\PanelUser;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Services\Photos\PhotoRenditions;
use App\Services\Photos\WebpMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Aviso de direito autoral: texto da marca, XMP nas prévias, campo do painel e regeração. */
class PhotoCopyrightTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private string $originals;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('kena.photos.disk'));
        $this->originals = sys_get_temp_dir().'/kena-orig-'.bin2hex(random_bytes(4));
        mkdir($this->originals);
        config(['kena.photos.originals_path' => $this->originals]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->originals.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->originals);
        parent::tearDown();
    }

    private function gallery(?string $holder = null): PhotoGallery
    {
        return PhotoGallery::create([
            'event_id' => $this->makeSession()->event->id,
            'status' => 'draft',
            'copyright_holder' => $holder,
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
        ]);
    }

    private function image(int $w = 900, int $h = 600): \GdImage
    {
        $image = imagecreatetruecolor($w, $h);
        imagefilledrectangle($image, 0, 0, $w, $h, imagecolorallocate($image, 90, 140, 200));

        return $image;
    }

    private function storedXmp(string $path): ?string
    {
        return WebpMetadata::readXmp(Storage::disk(config('kena.photos.disk'))->get($path));
    }

    public function test_notice_line_carries_the_holder_and_the_law(): void
    {
        $this->assertSame(
            '© Studio Luz  ·  Uso só com compra  ·  Lei 9.610/98',
            PhotoRenditions::notice('Studio Luz'),
        );
    }

    public function test_gallery_falls_back_to_the_configured_holder(): void
    {
        config(['kena.photos.copyright_holder' => 'Kena']);

        $this->assertSame('Kena', $this->gallery()->copyrightHolder());
        $this->assertSame('Kena', $this->gallery('   ')->copyrightHolder());
        $this->assertSame('Escola Dança', $this->gallery(' Escola Dança ')->copyrightHolder());
    }

    public function test_preview_and_thumb_store_copyright_xmp_that_reads_back(): void
    {
        $renditions = new PhotoRenditions;
        $source = $this->image();

        $renditions->preview($source, 'fotos/1/a-preview.webp', 'Studio Luz & Filhos');
        $renditions->thumb($source, 'fotos/1/a-thumb.webp', 'Studio Luz & Filhos');

        foreach (['fotos/1/a-preview.webp', 'fotos/1/a-thumb.webp'] as $path) {
            $xmp = $this->storedXmp($path);
            $this->assertNotNull($xmp, "{$path} sem XMP");
            $this->assertStringContainsString('xmpRights:Marked="True"', $xmp);

            $doc = new \DOMDocument;
            $this->assertTrue($doc->loadXML(preg_replace('/<\?xpacket[^>]*\?>/', '', $xmp)), 'XMP não é XML válido');
            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
            $xp->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
            $rights = $xp->evaluate('string(//dc:rights//rdf:li)');
            $this->assertStringContainsString('Studio Luz & Filhos', $rights);
            $this->assertStringContainsString('Lei 9.610/98', $rights);
        }

        // O arquivo continua sendo um WebP que os navegadores abrem.
        $bytes = Storage::disk(config('kena.photos.disk'))->get('fotos/1/a-preview.webp');
        $this->assertNotFalse(imagecreatefromstring($bytes));
        $info = getimagesizefromstring($bytes);
        $this->assertSame([900, 600], [$info[0], $info[1]]);
    }

    public function test_renditions_without_a_holder_use_the_default(): void
    {
        config(['kena.photos.copyright_holder' => 'Kena']);

        (new PhotoRenditions)->preview($this->image(), 'fotos/1/b-preview.webp');

        $this->assertStringContainsString('© '.date('Y').' Kena.', (string) $this->storedXmp('fotos/1/b-preview.webp'));
    }

    public function test_embedding_xmp_twice_keeps_a_single_packet(): void
    {
        ob_start();
        imagewebp($this->image(64, 48), null, 70);
        $webp = (string) ob_get_clean();

        $once = WebpMetadata::embedXmp($webp, WebpMetadata::copyrightPacket('A', 2026));
        $twice = WebpMetadata::embedXmp($once, WebpMetadata::copyrightPacket('B', 2026));

        $this->assertNull(WebpMetadata::readXmp($webp));
        $this->assertStringContainsString('© 2026 B.', (string) WebpMetadata::readXmp($twice));
        $this->assertSame(1, substr_count($twice, 'XMP '));
    }

    // --- Painel ------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'description' => 'Registros.',
            'tiers' => [['min_qty' => 1, 'price' => '35']],
        ], $extra);
    }

    private function asOrganizer(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel');
    }

    private function addPhoto(PhotoGallery $gallery): Photo
    {
        return Photo::create([
            'photo_gallery_id' => $gallery->id,
            'filename' => 'a.jpg',
            'original_ref' => 'a.jpg',
            'preview_path' => "fotos/{$gallery->id}/a-preview.webp",
            'thumb_path' => "fotos/{$gallery->id}/a-thumb.webp",
            'width' => 720,
            'height' => 480,
        ]);
    }

    public function test_panel_saves_the_holder_and_shows_it_in_the_form(): void
    {
        $this->asOrganizer();
        $gallery = $this->gallery();
        $event = $gallery->event;

        $this->put(route('admin.photos.save', $event), $this->payload(['copyright_holder' => '  Studio Luz  ']))
            ->assertRedirect();

        $this->assertSame('Studio Luz', $gallery->fresh()->copyright_holder);

        $this->get(route('admin.photos.edit', $event))
            ->assertInertia(fn ($page) => $page
                ->where('form.copyright_holder', 'Studio Luz')
                ->where('form.default_holder', 'Kena'));
    }

    public function test_panel_clears_the_holder_when_left_empty(): void
    {
        $this->asOrganizer();
        $gallery = $this->gallery('Studio Luz');

        $this->put(route('admin.photos.save', $gallery->event), $this->payload(['copyright_holder' => '']))
            ->assertRedirect();

        $this->assertNull($gallery->fresh()->copyright_holder);
    }

    public function test_panel_rejects_a_holder_longer_than_80_characters(): void
    {
        $this->asOrganizer();
        $gallery = $this->gallery();

        $this->put(route('admin.photos.save', $gallery->event), $this->payload(['copyright_holder' => str_repeat('a', 81)]))
            ->assertSessionHasErrors('copyright_holder');
    }

    public function test_changing_the_holder_queues_a_regeneration_only_when_it_matters(): void
    {
        Queue::fake();
        $this->asOrganizer();
        $gallery = $this->gallery();
        $event = $gallery->event;

        // Sem fotos, não há o que refazer.
        $this->put(route('admin.photos.save', $event), $this->payload(['copyright_holder' => 'Studio Luz']));
        Queue::assertNothingPushed();

        $this->addPhoto($gallery);

        // Mesmo titular: nada a refazer.
        $this->put(route('admin.photos.save', $event), $this->payload(['copyright_holder' => 'Studio Luz']));
        Queue::assertNothingPushed();

        $this->put(route('admin.photos.save', $event), $this->payload(['copyright_holder' => 'Escola Dança']))
            ->assertSessionHas('status');
        Queue::assertPushed(RegeneratePhotoGalleryRenditions::class, fn ($job) => $job->galleryId === $gallery->id);
    }

    // --- Regerar -----------------------------------------------------------

    public function test_regenerate_command_redraws_previews_from_the_originals(): void
    {
        $gallery = $this->gallery('Escola Dança');
        $photo = $this->addPhoto($gallery);
        $other = Photo::create([
            'photo_gallery_id' => $gallery->id, 'filename' => 'demo.jpg', 'original_ref' => null,
            'preview_path' => 'fotos/demo-preview.webp', 'thumb_path' => 'fotos/demo-thumb.webp',
            'width' => 10, 'height' => 10,
        ]);
        imagejpeg($this->image(1600, 1000), $this->originals.'/a.jpg');

        // Prévia antiga, de outro titular.
        (new PhotoRenditions)->preview($this->image(), $photo->preview_path, 'Antigo');

        $this->artisan('fotos:regerar', ['slug' => $gallery->event->slug])
            ->expectsOutputToContain('1 regerada(s), 1 sem original')
            ->assertSuccessful();

        foreach ([$photo->preview_path, $photo->thumb_path] as $path) {
            $this->assertStringContainsString('Escola Dança', (string) $this->storedXmp($path));
        }
        $this->assertStringNotContainsString('Antigo', (string) $this->storedXmp($photo->preview_path));
        $this->assertFalse(Storage::disk(config('kena.photos.disk'))->exists($other->preview_path));
    }

    public function test_regenerate_command_fails_for_unknown_event(): void
    {
        $this->artisan('fotos:regerar', ['slug' => 'nao-existe'])->assertFailed();
        $this->assertNotNull(Event::query());
    }
}
