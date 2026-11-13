<?php

namespace Tests\Feature\Kena;

use App\Models\Event;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Models\Venue;
use App\Services\Photos\ImportResult;
use App\Services\Photos\LightroomFixtureWriter;
use App\Services\Photos\LocalFolderSource;
use App\Services\Photos\PhotoImporter;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoImporterTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private PhotoGallery $gallery;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->dir = sys_get_temp_dir().'/kena-import-'.bin2hex(random_bytes(4));
        mkdir($this->dir);

        $venue = Venue::create(['name' => 'Teatro', 'city' => 'Brasília', 'state' => 'DF']);
        $event = Event::create([
            'venue_id' => $venue->id,
            'slug' => 'quebra-nozes',
            'title' => 'Quebra-Nozes',
            'kicker' => 'Teste',
            'description' => 'Descrição.',
            'status' => 'on_sale',
        ]);
        $this->gallery = PhotoGallery::create([
            'event_id' => $event->id,
            'status' => 'draft',
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function test_group_comes_from_the_subfolder_and_root_files_have_none(): void
    {
        $this->photo('Baby Class/a.jpg');
        $this->photo('Baby Class/b.jpg');
        $this->photo('Estúdio/c.jpg');
        $this->photo('solta.jpg');

        $result = $this->import();

        $this->assertSame(4, $result->imported);
        $this->assertSame(['Baby Class', 'Estúdio'], $this->gallery->groups()->pluck('name')->all());
        $this->assertSame(2, Photo::whereHas('group', fn ($q) => $q->where('name', 'Baby Class'))->count());
        $this->assertNull(Photo::where('filename', 'solta.jpg')->first()->photo_group_id);
        $this->assertSame('Baby Class/a.jpg', Photo::where('filename', 'a.jpg')->first()->original_ref);
    }

    public function test_creates_people_with_regions_and_accented_names(): void
    {
        $region = ['x' => 0.5, 'y' => 0.4, 'w' => 0.2, 'h' => 0.3];
        $this->photo('Juvenil/a.jpg', ['Júlia Carvalho' => $region, 'Cecília Ramos' => null]);
        $this->photo('Juvenil/b.jpg', ['Júlia Carvalho' => $region]);

        $this->import();

        $julia = PhotoPerson::where('name', 'Júlia Carvalho')->firstOrFail();
        $this->assertSame(2, $julia->photos()->count());
        $this->assertEquals($region, json_decode($julia->photos()->first()->pivot->region, true));
        $this->assertNotNull($julia->face_path);
        Storage::disk('public')->assertExists($julia->face_path);

        // Cecília só existe como palavra-chave: sem região, sem recorte.
        $cecilia = PhotoPerson::where('name', 'Cecília Ramos')->firstOrFail();
        $this->assertNull($cecilia->face_path);
        $this->assertNull($cecilia->photos()->first()->pivot->region);
    }

    public function test_renditions_are_stored_for_each_photo(): void
    {
        $this->photo('Infantil/a.jpg');

        $this->import();

        $photo = Photo::firstOrFail();
        Storage::disk('public')->assertExists($photo->preview_path);
        Storage::disk('public')->assertExists($photo->thumb_path);
        $this->assertSame(300, $photo->width);
        $this->assertSame(200, $photo->height);
    }

    public function test_reimporting_is_idempotent_and_picks_up_only_new_files(): void
    {
        $this->photo('Baby Class/a.jpg', ['Alice Moura' => $this->region()]);
        $this->photo('Baby Class/b.jpg', ['Alice Moura' => $this->region()]);
        $first = $this->import();
        $this->assertSame(2, $first->imported);

        $again = $this->import();
        $this->assertSame(0, $again->imported);
        $this->assertSame(2, $again->skipped);
        $this->assertSame(2, Photo::count());
        $this->assertSame(1, PhotoPerson::count());
        $this->assertSame(1, $this->gallery->groups()->count());

        $this->photo('Baby Class/c.jpg', ['Alice Moura' => $this->region(), 'Helena Costa' => $this->region()]);
        $third = $this->import();

        $this->assertSame(1, $third->imported);
        $this->assertSame(2, $third->skipped);
        $this->assertSame(1, $third->people);
        $this->assertSame(3, Photo::count());
        $this->assertSame(2, PhotoPerson::count());
        $this->assertSame([1, 2, 3], Photo::orderBy('position')->pluck('position')->all());
    }

    public function test_face_comes_from_the_largest_crop_even_across_imports(): void
    {
        $small = ['x' => 0.5, 'y' => 0.5, 'w' => 0.05, 'h' => 0.08];
        $big = ['x' => 0.5, 'y' => 0.5, 'w' => 0.2, 'h' => 0.3];
        $bigger = ['x' => 0.5, 'y' => 0.5, 'w' => 0.3, 'h' => 0.45];

        $this->photo('T/a.jpg', ['Alice Moura' => $small], [200, 0, 0]);   // vermelha
        $this->photo('T/b.jpg', ['Alice Moura' => $big], [0, 200, 0]);     // verde
        $this->import();
        $this->assertSame('green', $this->faceColor('Alice Moura'));

        // Nova foto com rosto menor não troca o rosto.
        $this->photo('T/c.jpg', ['Alice Moura' => $small], [200, 0, 0]);
        $this->import();
        $this->assertSame('green', $this->faceColor('Alice Moura'));

        // Nova foto com rosto maior troca.
        $this->photo('T/d.jpg', ['Alice Moura' => $bigger], [0, 0, 200]);  // azul
        $this->import();
        $this->assertSame('blue', $this->faceColor('Alice Moura'));
    }

    public function test_exif_orientation_is_applied_before_cropping_the_face(): void
    {
        // Foto de pé 100x200: metade de cima vermelha, a de baixo azul. O rosto está em cima.
        $upright = imagecreatetruecolor(100, 200);
        imagefilledrectangle($upright, 0, 0, 99, 99, imagecolorallocate($upright, 220, 0, 0));
        imagefilledrectangle($upright, 0, 100, 99, 199, imagecolorallocate($upright, 0, 0, 220));
        $region = ['x' => 0.5, 'y' => 0.25, 'w' => 0.3, 'h' => 0.15];

        LightroomFixtureWriter::write($this->dir.'/T/vertical.jpg', $upright, ['Alice Moura' => $region], [], 6);
        // O arquivo guardado é deitado (200x100): sem a correção o recorte pegaria outro lugar.
        $this->assertSame([200, 100], array_slice(getimagesize($this->dir.'/T/vertical.jpg'), 0, 2));

        $this->import();

        $photo = Photo::firstOrFail();
        $this->assertSame(100, $photo->width);
        $this->assertSame(200, $photo->height);
        $this->assertSame('red', $this->faceColor('Alice Moura'));
    }

    public function test_a_corrupt_file_fails_alone_and_does_not_stop_the_rest(): void
    {
        $this->photo('T/a.jpg');
        file_put_contents($this->dir.'/T/quebrada.jpg', 'nao e jpeg');
        $this->photo('T/z.jpg');

        $result = $this->import();

        $this->assertSame(2, $result->imported);
        $this->assertSame(['T/quebrada.jpg'], array_keys($result->failed));
        $this->assertSame(2, Photo::count());
        // Rodar de novo tenta a quebrada outra vez, sem duplicar as boas.
        $this->assertSame(0, $this->import()->imported);
    }

    public function test_command_imports_creates_draft_gallery_and_publishes_on_request(): void
    {
        $this->gallery->delete();
        $this->photo('Baby Class/a.jpg', ['Alice Moura' => $this->region()]);

        $this->artisan('fotos:importar', ['slug' => 'quebra-nozes', 'pasta' => $this->dir])
            ->expectsOutputToContain('1 importadas, 0 já existiam')
            ->assertExitCode(0);

        $gallery = PhotoGallery::firstOrFail();
        $this->assertSame('draft', $gallery->status);
        $this->assertSame(3500, $gallery->price_tiers[0]['unit_price_cents']);
        $this->assertSame(2000, $gallery->price_tiers[1]['unit_price_cents']);

        $this->artisan('fotos:importar', ['slug' => 'quebra-nozes', 'pasta' => $this->dir, '--publicar' => true])
            ->expectsOutputToContain('0 importadas, 1 já existiam')
            ->assertExitCode(0);

        $this->assertSame('published', $gallery->fresh()->status);
        $this->assertSame(1, Photo::count());
    }

    public function test_command_rejects_unknown_event_and_missing_folder(): void
    {
        $this->artisan('fotos:importar', ['slug' => 'nao-existe', 'pasta' => $this->dir])->assertExitCode(1);
        $this->artisan('fotos:importar', ['slug' => 'quebra-nozes', 'pasta' => $this->dir.'/nada'])->assertExitCode(1);
    }

    /** @return array{x: float, y: float, w: float, h: float} */
    private function region(): array
    {
        return ['x' => 0.5, 'y' => 0.5, 'w' => 0.2, 'h' => 0.3];
    }

    /**
     * @param  array<string, array{x: float, y: float, w: float, h: float}|null>  $people
     * @param  array{0: int, 1: int, 2: int}  $color
     */
    private function photo(string $relative, array $people = [], array $color = [90, 90, 90]): void
    {
        $image = imagecreatetruecolor(300, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$color));

        LightroomFixtureWriter::write($this->dir.'/'.$relative, $image, $people);
    }

    private function import(): ImportResult
    {
        return app(PhotoImporter::class)->import($this->gallery->fresh(), new LocalFolderSource($this->dir));
    }

    /** Cor dominante do centro do recorte do rosto da pessoa. */
    private function faceColor(string $name): string
    {
        $person = PhotoPerson::where('name', $name)->firstOrFail();
        $image = imagecreatefromstring(Storage::disk('public')->get($person->face_path));
        $this->assertInstanceOf(GdImage::class, $image);

        $rgb = imagecolorat($image, 96, 96);
        $channels = ['red' => ($rgb >> 16) & 255, 'green' => ($rgb >> 8) & 255, 'blue' => $rgb & 255];
        arsort($channels);

        return array_key_first($channels);
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            is_dir("$dir/$entry") ? $this->removeDir("$dir/$entry") : unlink("$dir/$entry");
        }
        rmdir($dir);
    }
}
