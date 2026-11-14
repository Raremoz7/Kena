<?php

namespace Tests\Feature\Kena;

use App\Mail\PhotoGalleryPublishedMail;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Fotos só aparecem no site depois do espetáculo (com galeria publicada), e o
 * painel avisa uma vez, por e-mail, quem comprou ingresso.
 */
class PhotoGalleryLaunchTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private EventSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $this->makeSession(4);
    }

    private function gallery(string $status = 'draft'): PhotoGallery
    {
        $gallery = PhotoGallery::create([
            'event_id' => $this->session->event_id,
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
            'price_tiers' => [
                ['min_qty' => 1, 'unit_price_cents' => 3500],
                ['min_qty' => 10, 'unit_price_cents' => 2000],
            ],
        ]);
        Photo::create([
            'photo_gallery_id' => $gallery->id, 'filename' => 'a.jpg',
            'preview_path' => 'p.webp', 'thumb_path' => 't.webp', 'width' => 10, 'height' => 10,
        ]);

        return $gallery;
    }

    private function buyer(string $status = Order::STATUS_PAID, ?EventSession $session = null, string $kind = Order::KIND_TICKETS): User
    {
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id, 'session_id' => ($session ?? $this->session)->id, 'kind' => $kind,
            'reference' => 'KNA-'.$user->id.'-'.$status, 'subtotal_cents' => 0, 'discount_cents' => 0,
            'fee_cents' => 0, 'total_cents' => 0, 'status' => $status,
        ]);

        return $user;
    }

    // --- Fotos fora do site antes da galeria ----------------------------------

    public function test_photos_stay_hidden_until_a_gallery_is_published(): void
    {
        $this->gallery('draft');

        $this->get('/eventos')->assertInertia(fn (AssertableInertia $p) => $p->where('photosAvailable', false));
        $this->get('/fotos')->assertNotFound();
    }

    public function test_published_gallery_brings_photos_to_the_menu(): void
    {
        $this->gallery('published');

        $this->get('/eventos')->assertInertia(fn (AssertableInertia $p) => $p->where('photosAvailable', true));
        $this->get('/fotos')->assertOk();
    }

    public function test_menu_hides_photos_even_for_panel_until_published(): void
    {
        $this->gallery('draft');
        $panel = PanelUser::factory()->create();

        $this->actingAs($panel, 'painel')->get('/eventos')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('photosAvailable', false));
        // A equipe ainda abre a lista pelo endereço, para conferir.
        $this->actingAs($panel, 'painel')->get('/fotos')->assertOk();
    }

    // --- Aviso para quem comprou ingresso -------------------------------------

    public function test_notify_mails_each_paid_ticket_buyer_once(): void
    {
        Mail::fake();
        $gallery = $this->gallery('published');
        $paid = $this->buyer();
        // Segundo pedido pago da mesma pessoa: continua um e-mail só.
        Order::create([
            'user_id' => $paid->id, 'session_id' => $this->session->id, 'kind' => Order::KIND_TICKETS,
            'reference' => 'KNA-DUP', 'subtotal_cents' => 0, 'discount_cents' => 0,
            'fee_cents' => 0, 'total_cents' => 0, 'status' => Order::STATUS_PAID,
        ]);
        $refunded = $this->buyer(Order::STATUS_REFUNDED);
        $otherEvent = $this->buyer(session: $this->makeSession(1));

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post("/painel/fotos/{$this->session->event_id}/avisar-compradores")
            ->assertSessionHas('status', 'Aviso enviado para 1 pessoa.');

        Mail::assertQueued(PhotoGalleryPublishedMail::class, 1);
        Mail::assertQueued(PhotoGalleryPublishedMail::class, fn ($m) => $m->hasTo($paid->email));
        Mail::assertNotQueued(PhotoGalleryPublishedMail::class, fn ($m) => $m->hasTo($refunded->email) || $m->hasTo($otherEvent->email));

        $gallery->refresh();
        $this->assertNotNull($gallery->buyers_notified_at);
        $this->assertSame(1, $gallery->buyers_notified_count);
    }

    public function test_notify_cannot_be_sent_twice(): void
    {
        Mail::fake();
        $this->gallery('published');
        $this->buyer();
        $panel = PanelUser::factory()->create();
        $url = "/painel/fotos/{$this->session->event_id}/avisar-compradores";

        $this->actingAs($panel, 'painel')->post($url);
        $this->actingAs($panel, 'painel')->post($url)->assertSessionHasErrors('notify');

        Mail::assertQueued(PhotoGalleryPublishedMail::class, 1);
    }

    public function test_notify_needs_a_published_gallery(): void
    {
        Mail::fake();
        $gallery = $this->gallery('draft');
        $this->buyer();

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post("/painel/fotos/{$this->session->event_id}/avisar-compradores")
            ->assertSessionHasErrors('notify');

        Mail::assertNothingQueued();
        $this->assertNull($gallery->fresh()->buyers_notified_at);
    }

    public function test_mail_shows_price_code_and_opens_the_gallery(): void
    {
        $gallery = $this->gallery('published');
        $user = $this->buyer();

        $mail = new PhotoGalleryPublishedMail($gallery, $user);

        $mail->assertHasSubject('As fotos de '.$this->session->event->title.' chegaram');
        $mail->assertSeeInHtml('R$ 35');
        $mail->assertSeeInHtml('cada uma sai por R$ 20');
        $mail->assertSeeInHtml($gallery->access_code);
        $mail->assertSeeInHtml('Abrir a galeria de fotos');
        $mail->assertSeeInHtml('/fotos/'.$this->session->event->slug);
        $mail->assertDontSeeInHtml('—');
    }
}
