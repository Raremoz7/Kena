<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\Order;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\PhotoPerson;
use App\Models\PhotoPrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** A galeria mostra rosto e nome de criança: só abre com código ou ingresso pago. */
class PhotoGalleryAccessTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private PhotoGallery $gallery;

    private Photo $photo;

    private PhotoPerson $person;

    private string $slug;

    private EventSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('photos');
        $session = $this->makeSession(2);
        $this->session = $session;
        $this->slug = $session->event->slug;

        $this->gallery = PhotoGallery::create([
            'event_id' => $session->event_id,
            'status' => 'published',
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
            'published_at' => now(),
        ]);
        $this->person = $this->gallery->people()->create(['name' => 'Alice Moura', 'face_path' => 'fotos/1/rostos/1.webp']);
        $this->photo = Photo::create([
            'photo_gallery_id' => $this->gallery->id,
            'filename' => 'a.jpg',
            'preview_path' => 'fotos/1/0001-preview.webp',
            'thumb_path' => 'fotos/1/0001-thumb.webp',
            'width' => 100,
            'height' => 80,
            'position' => 1,
        ]);
        $this->photo->people()->attach($this->person->id);

        foreach (['fotos/1/0001-preview.webp', 'fotos/1/0001-thumb.webp', 'fotos/1/rostos/1.webp'] as $path) {
            Storage::disk('photos')->put($path, 'imagem');
        }
    }

    private function buyerOfEvent(string $status = Order::STATUS_PAID): User
    {
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id, 'session_id' => $this->session->id,
            'reference' => 'KNA-FOTO-'.$user->id, 'subtotal_cents' => 0, 'discount_cents' => 0,
            'fee_cents' => 0, 'total_cents' => 0, 'status' => $status,
        ]);

        return $user;
    }

    public function test_code_is_generated_and_short(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z]+-\d{6}$/', $this->gallery->access_code);
    }

    public function test_gallery_locks_after_too_many_wrong_codes_from_any_ip(): void
    {
        // Cada tentativa vem de um IP diferente: o limite da rota por IP não pega,
        // a trava por galeria pega.
        for ($i = 0; $i < 50; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.{$i}.1"])
                ->post("/fotos/{$this->slug}/acesso", ['code' => 'ERRADO-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT)]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code])
            ->assertSessionHasErrors('code');

        $this->get("/fotos/{$this->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page->component('buyer/photo-gallery-locked'));
    }

    public function test_visitor_gets_the_entry_screen_without_photos_or_faces(): void
    {
        $this->get("/fotos/{$this->slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('buyer/photo-gallery-locked')
                ->missing('photos')
                ->missing('people')
                ->where('event.slug', $this->slug));
    }

    public function test_visitor_cannot_download_images(): void
    {
        $this->get("/fotos/midia/{$this->photo->id}/thumb")->assertNotFound();
        $this->get("/fotos/midia/{$this->photo->id}/preview")->assertNotFound();
        $this->get("/fotos/midia/rosto/{$this->person->id}")->assertNotFound();
    }

    public function test_images_are_not_on_the_public_disk(): void
    {
        $this->assertFalse(Storage::disk('public')->exists($this->photo->thumb_path));
        $this->assertSame('private', config('filesystems.disks.photos.visibility'));
    }

    public function test_list_hides_cover_photos_from_visitor(): void
    {
        $this->get('/fotos')->assertInertia(fn (AssertableInertia $p) => $p
            ->component('buyer/photos')
            ->where('galleries.0.locked', true)
            ->where('galleries.0.covers', [])
            ->where('galleries.0.event.slug', $this->slug));
    }

    public function test_right_code_unlocks_the_browser_session(): void
    {
        $this->post("/fotos/{$this->slug}/acesso", ['code' => strtolower($this->gallery->access_code)])
            ->assertRedirect("/fotos/{$this->slug}");

        $this->get("/fotos/{$this->slug}")->assertInertia(fn (AssertableInertia $p) => $p
            ->component('buyer/photo-gallery')
            ->has('photos', 1)
            ->has('people', 1));

        $this->get("/fotos/midia/{$this->photo->id}/thumb")->assertOk()->assertHeader('Cache-Control', 'max-age=86400, private');
        $this->get("/fotos/midia/{$this->photo->id}/preview")->assertOk();
        $this->get("/fotos/midia/rosto/{$this->person->id}")->assertOk();

        $this->get('/fotos')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('galleries.0.locked', false)
            ->has('galleries.0.covers', 1));
    }

    public function test_code_ignores_spaces_hyphen_and_case(): void
    {
        $messy = strtolower(str_replace('-', ' ', $this->gallery->access_code));

        $this->post("/fotos/{$this->slug}/acesso", ['code' => $messy])->assertRedirect();
        $this->get("/fotos/{$this->slug}")->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery'));
    }

    public function test_wrong_code_keeps_the_gallery_closed(): void
    {
        $this->post("/fotos/{$this->slug}/acesso", ['code' => 'ERRADO-0000'])->assertSessionHasErrors('code');

        $this->get("/fotos/{$this->slug}")->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery-locked'));
        $this->get("/fotos/midia/{$this->photo->id}/thumb")->assertNotFound();
    }

    public function test_code_of_another_gallery_does_not_open_this_one(): void
    {
        $other = $this->makeSession(1);
        $otherGallery = PhotoGallery::create([
            'event_id' => $other->event_id, 'status' => 'published',
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 3500]],
        ]);

        $this->post("/fotos/{$this->slug}/acesso", ['code' => $otherGallery->access_code])->assertSessionHasErrors('code');
    }

    public function test_code_typed_while_logged_in_is_saved_on_the_account(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code]);

        $this->assertTrue($this->gallery->unlockedBy()->whereKey($user->id)->exists());

        // Outro aparelho (sessão nova), mesma conta: continua liberado.
        $this->flushSession();
        $this->actingAs($user)->get("/fotos/{$this->slug}")
            ->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery'));
    }

    public function test_ticket_buyer_gets_in_without_code(): void
    {
        $user = $this->buyerOfEvent();

        $this->actingAs($user)->get("/fotos/{$this->slug}")
            ->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery')->has('photos', 1));
        $this->actingAs($user)->get("/fotos/midia/{$this->photo->id}/thumb")->assertOk();
    }

    public function test_refunded_or_other_event_orders_do_not_count(): void
    {
        $refunded = $this->buyerOfEvent(Order::STATUS_REFUNDED);
        $this->actingAs($refunded)->get("/fotos/{$this->slug}")
            ->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery-locked'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get("/fotos/midia/{$this->photo->id}/thumb")->assertNotFound();
    }

    public function test_draft_gallery_images_stay_closed_even_with_access(): void
    {
        $user = $this->buyerOfEvent();
        $this->gallery->update(['status' => 'draft']);

        $this->actingAs($user)->get("/fotos/midia/{$this->photo->id}/thumb")->assertNotFound();
    }

    public function test_code_attempts_are_throttled(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)->post("/fotos/{$this->slug}/acesso", ['code' => 'ERRADO-000'.$i])
                ->assertSessionHasErrors('code');
        }

        // A sexta tentativa já cai no limite, mesmo com o código certo.
        $this->actingAs($user)->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code])
            ->assertSessionHasErrors('code');
        $this->assertFalse($this->gallery->unlockedBy()->whereKey($user->id)->exists());
    }

    public function test_guessing_with_fresh_sessions_is_throttled_by_ip(): void
    {
        // Quem descarta o cookie a cada tentativa cai no teto por IP.
        for ($i = 0; $i < 30; $i++) {
            $this->flushSession();
            $this->post("/fotos/{$this->slug}/acesso", ['code' => 'ERRADO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)]);
        }

        $this->flushSession();
        $this->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code])
            ->assertSessionHasErrors('code');
        $this->get("/fotos/{$this->slug}")->assertInertia(fn (AssertableInertia $p) => $p->component('buyer/photo-gallery-locked'));
    }

    public function test_image_responds_304_when_unchanged(): void
    {
        $this->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code]);
        $etag = $this->get("/fotos/midia/{$this->photo->id}/thumb")->headers->get('ETag');

        $this->withHeader('If-None-Match', $etag)
            ->get("/fotos/midia/{$this->photo->id}/thumb")
            ->assertStatus(304);
    }

    public function test_privacy_request_needs_access_and_is_saved(): void
    {
        $payload = ['guardian_name' => 'Maria', 'contact' => 'maria@exemplo.test', 'child_name' => 'Alice Moura'];

        $this->post("/fotos/{$this->slug}/privacidade", $payload)->assertNotFound();
        $this->assertSame(0, PhotoPrivacyRequest::count());

        $this->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code]);
        $this->post("/fotos/{$this->slug}/privacidade", $payload)->assertSessionHas('status');

        $this->assertDatabaseHas('photo_privacy_requests', [
            'photo_gallery_id' => $this->gallery->id,
            'child_name' => 'Alice Moura',
            'status' => 'pending',
        ]);
    }

    public function test_privacy_request_validates_required_fields(): void
    {
        $this->post("/fotos/{$this->slug}/acesso", ['code' => $this->gallery->access_code]);

        $this->post("/fotos/{$this->slug}/privacidade", [])
            ->assertSessionHasErrors(['guardian_name', 'contact', 'child_name']);
    }
}
