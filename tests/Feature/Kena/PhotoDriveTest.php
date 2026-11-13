<?php

namespace Tests\Feature\Kena;

use App\Jobs\ImportPhotosFromDrive;
use App\Models\Event;
use App\Models\PanelUser;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\Venue;
use App\Services\Photos\Drive\DriveClient;
use App\Services\Photos\Drive\DriveException;
use App\Services\Photos\Drive\DriveFolderSource;
use App\Services\Photos\Drive\DrivePhotoOriginalStore;
use App\Services\Photos\ImportResult;
use App\Services\Photos\LightroomFixtureWriter;
use App\Services\Photos\PhotoImporter;
use App\Services\Photos\PhotoOriginalStore;
use App\Services\Photos\RoutingPhotoOriginalStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fotos no Google Drive: pasta, importação e entrega do original, com a API do Drive simulada. */
class PhotoDriveTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = 'RootFolderId1234567890';

    private const EMAIL = 'kena-fotos@projeto.iam.gserviceaccount.com';

    private string $credentials;

    private PhotoGallery $gallery;

    /** @var array<string, array{name: string, mime: string, parent: ?string, body: ?string}> */
    private array $files = [];

    /** @var list<string> ids baixados (alt=media) */
    private array $downloaded = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('kena.photos.disk'));
        Cache::flush();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->credentials = sys_get_temp_dir().'/kena-gcred-'.bin2hex(random_bytes(4)).'.json';
        file_put_contents($this->credentials, json_encode(['client_email' => self::EMAIL, 'private_key' => $pem]));
        $this->useCredentials($this->credentials);

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
            'drive_folder' => self::ROOT,
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
        ]);

        $this->files[self::ROOT] = ['name' => 'Quebra-Nozes', 'mime' => DriveClient::FOLDER_MIME, 'parent' => null, 'body' => null];
        $this->fakeDrive();
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);
        parent::tearDown();
    }

    public function test_folder_id_is_extracted_from_links_and_plain_ids(): void
    {
        $id = '1AbCdEfGhIjKlMnOpQrStUvWxYz_-0123';

        $this->assertSame($id, DriveFolderSource::extractId("https://drive.google.com/drive/folders/{$id}"));
        $this->assertSame($id, DriveFolderSource::extractId("https://drive.google.com/drive/u/0/folders/{$id}?usp=sharing"));
        $this->assertSame($id, DriveFolderSource::extractId("https://drive.google.com/drive/folders/{$id}?resourcekey=abc&usp=drive_link"));
        $this->assertSame($id, DriveFolderSource::extractId("https://drive.google.com/open?id={$id}"));
        $this->assertSame($id, DriveFolderSource::extractId("  {$id}  "));
        $this->assertNull(DriveFolderSource::extractId('https://example.com/qualquer-coisa'));
        $this->assertNull(DriveFolderSource::extractId('curto'));
    }

    public function test_lists_jpegs_recursively_with_the_first_subfolder_as_group(): void
    {
        $this->addFolder('baby', 'Baby Class');
        $this->addFolder('deep', 'Bastidores', parent: 'baby');
        $this->addJpeg('f1', 'b.jpg', parent: 'baby');
        $this->addJpeg('f2', 'a.jpg', parent: 'baby');
        $this->addJpeg('f3', 'c.JPG', parent: 'deep');
        $this->addJpeg('f4', 'solta.jpg', parent: self::ROOT);
        $this->addFile('f5', 'notas.txt', 'text/plain', parent: 'baby');

        $photos = iterator_to_array((new DriveFolderSource(app(DriveClient::class), self::ROOT))->photos(), false);

        $this->assertSame(
            ['drive:f2' => 'Baby Class', 'drive:f1' => 'Baby Class', 'drive:f3' => 'Baby Class', 'drive:f4' => null],
            collect($photos)->mapWithKeys(fn ($p) => [$p->ref => $p->group])->all(),
        );
        $this->assertSame([], $this->downloaded, 'listar não baixa nada');
    }

    public function test_imports_from_drive_and_a_second_run_downloads_nothing(): void
    {
        $this->addFolder('baby', 'Baby Class');
        $this->addJpeg('f1', 'a.jpg', parent: 'baby', people: ['Ana' => ['x' => 0.5, 'y' => 0.5, 'w' => 0.3, 'h' => 0.3]]);
        $this->addJpeg('f2', 'b.jpg', parent: 'baby');
        $this->addFile('f9', 'quebrado.jpg', 'image/jpeg', parent: 'baby', body: 'isto não é um jpeg');

        $first = $this->import();

        $this->assertSame(2, $first->imported);
        $this->assertSame(1, $first->people);
        $this->assertSame(['drive:f9'], array_keys($first->failed));
        $this->assertEqualsCanonicalizing(['drive:f1', 'drive:f2'], Photo::pluck('original_ref')->all());
        $this->assertSame('Baby Class', $this->gallery->groups()->first()->name);
        $this->assertSame(['Ana'], $this->gallery->people()->pluck('name')->all());
        $this->assertSame([], glob(sys_get_temp_dir().'/kena-drive-*') ?: [], 'os temporários baixados foram apagados');

        $this->downloaded = [];
        $second = $this->import();

        $this->assertSame(0, $second->imported);
        $this->assertSame(2, $second->skipped);
        $this->assertSame(['f9'], $this->downloaded, 'só a que falhou é tentada de novo');
        $this->assertSame(2, Photo::count());
    }

    public function test_original_is_delivered_from_drive_through_the_server(): void
    {
        $this->addJpeg('f1', 'a.jpg', parent: self::ROOT);
        $this->import();
        $photo = Photo::firstOrFail();
        $bytes = $this->files['f1']['body'];

        $store = app(PhotoOriginalStore::class);
        $this->assertInstanceOf(RoutingPhotoOriginalStore::class, $store);
        $this->assertTrue($store->has($photo));

        $original = $store->open($photo);
        $this->assertNotNull($original);
        $this->assertTrue($original->temporary);
        $this->assertSame($bytes, file_get_contents($original->path));
        $this->assertSame('a.jpg', $original->downloadName);

        $original->release();
        $this->assertFileDoesNotExist($original->path);
    }

    public function test_local_refs_still_go_to_the_local_store(): void
    {
        $dir = sys_get_temp_dir().'/kena-orig-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/x.jpg', 'local');
        config(['kena.photos.originals_path' => $dir]);

        $photo = Photo::create([
            'photo_gallery_id' => $this->gallery->id, 'filename' => 'x.jpg', 'original_ref' => 'x.jpg',
            'preview_path' => 'p', 'thumb_path' => 't', 'width' => 1, 'height' => 1,
        ]);

        $original = app(PhotoOriginalStore::class)->open($photo);
        $this->assertFalse($original->temporary);
        $this->assertSame('local', file_get_contents($original->path));
        $this->assertSame([], $this->downloaded);

        unlink($dir.'/x.jpg');
        rmdir($dir);
    }

    public function test_drive_failure_while_delivering_returns_null_instead_of_a_broken_download(): void
    {
        $photo = Photo::create([
            'photo_gallery_id' => $this->gallery->id, 'filename' => 'sumiu.jpg', 'original_ref' => 'drive:inexistente',
            'preview_path' => 'p', 'thumb_path' => 't', 'width' => 1, 'height' => 1,
        ]);

        $this->assertTrue(app(DrivePhotoOriginalStore::class)->has($photo));
        $this->assertNull(app(DrivePhotoOriginalStore::class)->open($photo));
        $this->assertSame([], glob(sys_get_temp_dir().'/kena-original-*') ?: []);
    }

    public function test_clear_error_when_the_folder_was_not_shared_with_the_service_account(): void
    {
        $source = new DriveFolderSource(app(DriveClient::class), 'PastaNaoCompartilhada12345');

        try {
            iterator_to_array($source->photos());
            $this->fail('Era para falhar.');
        } catch (DriveException $e) {
            $this->assertStringContainsString(self::EMAIL, $e->getMessage());
            $this->assertStringContainsString('Compartilhe a pasta', $e->getMessage());
        }
    }

    public function test_job_records_the_report_and_the_failure_reason(): void
    {
        $this->addJpeg('f1', 'a.jpg', parent: self::ROOT);

        (new ImportPhotosFromDrive($this->gallery->id))->handle(app(PhotoImporter::class), app(DriveClient::class));

        $gallery = $this->gallery->fresh();
        $this->assertSame('done', $gallery->import_status);
        $this->assertSame(1, $gallery->import_report['imported']);
        $this->assertNotNull($gallery->import_finished_at);

        $this->gallery->update(['drive_folder' => 'PastaNaoCompartilhada12345']);
        (new ImportPhotosFromDrive($this->gallery->id))->handle(app(PhotoImporter::class), app(DriveClient::class));

        $gallery = $this->gallery->fresh();
        $this->assertSame('failed', $gallery->import_status);
        $this->assertStringContainsString(self::EMAIL, $gallery->import_report['error']);
    }

    public function test_panel_saves_the_link_shows_the_service_email_and_queues_the_import(): void
    {
        Queue::fake();
        $this->actingAs(PanelUser::factory()->create(), 'painel');
        $event = $this->gallery->event;
        $id = '1AbCdEfGhIjKlMnOpQrStUvWxYz_-0123';

        $this->put(route('admin.photos.save', $event), [
            'drive_folder' => "https://drive.google.com/drive/u/0/folders/{$id}?usp=sharing",
            'tiers' => [['min_qty' => 1, 'price' => '35,00']],
        ])->assertSessionDoesntHaveErrors();
        $this->assertSame($id, $this->gallery->fresh()->drive_folder);

        $this->put(route('admin.photos.save', $event), [
            'drive_folder' => 'https://example.com/nada',
            'tiers' => [['min_qty' => 1, 'price' => '35,00']],
        ])->assertSessionHasErrors('drive_folder');

        $this->get(route('admin.photos.edit', $event))
            ->assertInertia(fn ($page) => $page
                ->where('drive.configured', true)
                ->where('drive.email', self::EMAIL)
                ->where('form.driveFolder', "https://drive.google.com/drive/folders/{$id}"));

        $this->post(route('admin.photos.import-drive', $event))->assertSessionDoesntHaveErrors();
        Queue::assertPushed(ImportPhotosFromDrive::class, fn ($job) => $job->galleryId === $this->gallery->id);
        $this->assertSame('queued', $this->gallery->fresh()->import_status);

        // Com uma importação em andamento, o botão não enfileira outra.
        $this->post(route('admin.photos.import-drive', $event))->assertSessionHasErrors('drive_folder');
        Queue::assertPushed(ImportPhotosFromDrive::class, 1);
    }

    public function test_panel_refuses_to_import_without_credentials_or_folder(): void
    {
        Queue::fake();
        $this->actingAs(PanelUser::factory()->create(), 'painel');
        $event = $this->gallery->event;

        $this->useCredentials(null);
        $this->post(route('admin.photos.import-drive', $event))->assertSessionHasErrors('drive_folder');
        $this->get(route('admin.photos.edit', $event))
            ->assertInertia(fn ($page) => $page->where('drive.configured', false)->where('drive.email', null));

        $this->useCredentials($this->credentials);
        $this->gallery->update(['drive_folder' => null]);
        $this->post(route('admin.photos.import-drive', $event))->assertSessionHasErrors('drive_folder');

        Queue::assertNothingPushed();
    }

    public function test_command_accepts_a_drive_link_instead_of_a_local_folder(): void
    {
        $this->addFolder('baby', 'Baby Class');
        $this->addJpeg('f1', 'a.jpg', parent: 'baby');

        $this->artisan('fotos:importar', ['slug' => 'quebra-nozes', 'pasta' => 'https://drive.google.com/drive/folders/'.self::ROOT])
            ->expectsOutputToContain('1 importadas, 0 já existiam, 0 falharam')
            ->assertSuccessful();

        $this->assertSame('drive:f1', Photo::firstOrFail()->original_ref);

        $this->artisan('fotos:importar', ['slug' => 'quebra-nozes', 'pasta' => 'https://drive.google.com/drive/folders/PastaNaoCompartilhada12345'])
            ->expectsOutputToContain(self::EMAIL)
            ->assertFailed();
    }

    private function import(): ImportResult
    {
        return app(PhotoImporter::class)->import(
            $this->gallery->fresh(),
            new DriveFolderSource(app(DriveClient::class), self::ROOT),
        );
    }

    private function useCredentials(?string $path): void
    {
        config(['kena.photos.drive.credentials' => $path]);
        $this->app->forgetInstance(DriveClient::class);
    }

    private function addFolder(string $id, string $name, string $parent = self::ROOT): void
    {
        $this->files[$id] = ['name' => $name, 'mime' => DriveClient::FOLDER_MIME, 'parent' => $parent, 'body' => null];
    }

    private function addFile(string $id, string $name, string $mime, string $parent, ?string $body = 'x'): void
    {
        $this->files[$id] = ['name' => $name, 'mime' => $mime, 'parent' => $parent, 'body' => $body];
    }

    /** @param  array<string, array{x: float, y: float, w: float, h: float}>  $people nome => região */
    private function addJpeg(string $id, string $name, string $parent, array $people = []): void
    {
        $image = imagecreatetruecolor(300, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 90, 90, 90));
        $tmp = tempnam(sys_get_temp_dir(), 'kena-fixture-');
        LightroomFixtureWriter::write($tmp, $image, $people);
        $body = (string) file_get_contents($tmp);
        unlink($tmp);

        $this->addFile($id, $name, 'image/jpeg', $parent, $body);
    }

    /** Simula a API REST do Drive: token, metadados, listagem e download. */
    private function fakeDrive(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-falso', 'expires_in' => 3600]);
            }

            $path = parse_url($url, PHP_URL_PATH);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if ($path === '/drive/v3/files') {
                preg_match("/'([^']+)' in parents/", $query['q'], $m);
                if (! isset($this->files[$m[1]]) || $this->files[$m[1]]['mime'] !== DriveClient::FOLDER_MIME) {
                    return Http::response(['error' => ['code' => 404, 'message' => 'File not found']], 404);
                }

                return Http::response(['files' => collect($this->files)
                    ->filter(fn ($f) => $f['parent'] === $m[1])
                    ->map(fn ($f, $id) => ['id' => $id, 'name' => $f['name'], 'mimeType' => $f['mime']])
                    ->values()->all()]);
            }

            $id = basename((string) $path);
            $file = $this->files[$id] ?? null;
            if ($file === null) {
                return Http::response(['error' => ['code' => 404, 'message' => 'File not found: '.$id]], 404);
            }

            if (($query['alt'] ?? null) === 'media') {
                $this->downloaded[] = $id;

                return Http::response((string) $file['body']);
            }

            return Http::response(['id' => $id, 'name' => $file['name'], 'mimeType' => $file['mime']]);
        });
    }
}
