<?php

namespace Tests\Feature\Kena;

use App\Mail\PhotosReadyMail;
use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Photo;
use App\Models\PhotoGallery;
use App\Models\User;
use App\Services\EmailVerificationCodeService;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentResult;
use App\Services\Photos\PhotoOrderService;
use App\Services\RefundService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

class PhotoPurchaseTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private FakeGateway $gateway;

    private string $originals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);

        $this->originals = sys_get_temp_dir().'/kena-originais-'.uniqid();
        File::ensureDirectoryExists($this->originals);
        config(['kena.photos.originals_path' => $this->originals]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->originals);

        parent::tearDown();
    }

    public function test_price_is_recalculated_on_the_server_from_the_gallery_tiers(): void
    {
        [$gallery, $photos] = $this->makeGallery(12);
        $user = User::factory()->create();

        // 9 fotos: preço avulso. O "total" mandado pelo navegador é ignorado.
        $this->actingAs($user)
            ->post(route('photos.checkout.start', $gallery->event->slug), [
                'photos' => $photos->take(9)->pluck('id')->all(),
                'total' => 1,
                'unit_price' => 1,
            ])
            ->assertRedirect();

        $nine = Order::photoOrders()->latest('id')->firstOrFail();
        $this->assertSame(9 * 3500, $nine->total_cents);
        $this->assertSame(0, $nine->discount_cents);

        // 10 fotos: TODAS saem pelo preço da faixa de 10.
        $this->actingAs($user)
            ->post(route('photos.checkout.start', $gallery->event->slug), [
                'photos' => $photos->take(10)->pluck('id')->all(),
            ])
            ->assertRedirect();

        $ten = Order::photoOrders()->latest('id')->firstOrFail();
        $this->assertSame(10 * 2000, $ten->total_cents);
        $this->assertSame(10 * 3500, $ten->subtotal_cents);
        $this->assertSame(10 * 1500, $ten->discount_cents);
        $this->assertSame([2000], $ten->photoItems()->pluck('unit_price_cents')->unique()->values()->all());

        // O pedido anterior (outra seleção) foi cancelado: só um pendente por galeria.
        $this->assertSame(Order::STATUS_CANCELLED, $nine->refresh()->status);
    }

    public function test_cart_cannot_include_photos_from_another_gallery(): void
    {
        [$gallery] = $this->makeGallery(2);
        [, $otherPhotos] = $this->makeGallery(1);

        $this->actingAs(User::factory()->create())
            ->post(route('photos.checkout.start', $gallery->event->slug), [
                'photos' => [$otherPhotos->first()->id],
            ])
            ->assertSessionHasErrors('photos');

        $this->assertSame(0, Order::photoOrders()->count());
    }

    public function test_approved_webhook_releases_the_photos_and_sends_the_email(): void
    {
        Mail::fake();
        [$gallery, $photos] = $this->makeGallery(3, withOriginals: true);
        $user = User::factory()->create(['cpf' => '52998224725']);
        $order = app(PhotoOrderService::class)->createFromSelection($user, $gallery, $photos->take(2)->pluck('id')->all());

        // Pix gerado pela rota de pagamento: fica pendente.
        $this->actingAs($user)
            ->postJson(route('photos.checkout.pay', $order->reference), ['method' => 'pix', 'document' => '52998224725'])
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('pix.copyPaste', '00020126-fake-pix');

        // Antes do pagamento: nada de download.
        $this->actingAs($user)->get(route('photos.mine.download', $photos[0]))->assertNotFound();

        $payload = ['type' => 'payment', 'id' => 'evt-foto-1', 'data' => ['id' => 'FAKE-PIX-'.$order->id]];
        app(WebhookService::class)->handleMercadoPago($payload);
        app(WebhookService::class)->handleMercadoPago($payload); // reentrega: idempotente

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        Mail::assertQueued(PhotosReadyMail::class, 1);

        $this->actingAs($user)
            ->getJson(route('photos.checkout.status', $order->reference))
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('redirect', route('photos.mine'));

        $download = $this->actingAs($user)->get(route('photos.mine.download', $photos[0]));
        $download->assertOk();
        $this->assertSame('original-'.$photos[0]->id, file_get_contents($download->baseResponse->getFile()->getPathname()));

        $this->actingAs($user)->get(route('photos.mine.zip', $gallery))->assertOk()
            ->assertHeader('content-type', 'application/zip');

        // Foto não comprada (3ª) segue fechada mesmo para quem comprou as outras.
        $this->actingAs($user)->get(route('photos.mine.download', $photos[2]))->assertNotFound();

        $this->actingAs($user)->get(route('photos.mine'))
            ->assertInertia(fn ($page) => $page
                ->component('buyer/my-photos')
                ->has('galleries', 1)
                ->has('galleries.0.photos', 2)
                ->where('galleries.0.photos.0.available', true));
    }

    public function test_card_payment_approved_on_the_spot_releases_the_photos(): void
    {
        [$gallery, $photos] = $this->makeGallery(1, withOriginals: true);
        $user = User::factory()->create(['cpf' => '52998224725']);
        $order = app(PhotoOrderService::class)->createFromSelection($user, $gallery, [$photos[0]->id]);

        $this->actingAs($user)
            ->postJson(route('photos.checkout.pay', $order->reference), ['method' => 'card', 'token' => 'tok', 'installments' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertSame(1, $this->gateway->cardCharges);
        $this->actingAs($user)->get(route('photos.mine.download', $photos[0]))->assertOk();
    }

    public function test_nobody_downloads_a_photo_they_did_not_buy(): void
    {
        [$gallery, $photos] = $this->makeGallery(2, withOriginals: true);
        $buyer = User::factory()->create();
        $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('photos.mine.download', $photos[0]))->assertNotFound();
        $this->actingAs($stranger)->get(route('photos.mine.zip', $gallery))->assertNotFound();

        // Pedido de outra pessoa nem aparece (404, sem confirmar que existe).
        $order = Order::photoOrders()->firstOrFail();
        $this->actingAs($stranger)->get(route('photos.checkout', $order->reference))->assertNotFound();
        $this->actingAs($stranger)->getJson(route('photos.checkout.status', $order->reference))->assertNotFound();

        // Sem login: vai para o login.
        auth()->guard('web')->logout();
        $this->get(route('photos.mine.download', $photos[0]))->assertRedirect(route('login'));

        // Quem comprou baixa a sua, não a outra.
        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertOk();
        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[1]))->assertNotFound();
    }

    public function test_refund_by_the_organizer_revokes_access(): void
    {
        Mail::fake();
        [$gallery, $photos] = $this->makeGallery(1, withOriginals: true);
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertOk();

        app(RefundService::class)->refundOrder($order, 'Pedido do comprador');

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(['FAKE-'.$order->id], $this->gateway->refunds);
        Mail::assertQueued(RefundConfirmedMail::class);
        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertNotFound();
        $this->actingAs($buyer)->get(route('photos.mine.zip', $gallery))->assertNotFound();
    }

    public function test_refund_made_at_mercado_pago_revokes_access_through_the_webhook(): void
    {
        Mail::fake();
        [$gallery, $photos] = $this->makeGallery(1, withOriginals: true);
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $this->gateway->fetchStatus = PaymentResult::STATUS_REFUNDED;
        app(WebhookService::class)->handleMercadoPago(['type' => 'payment', 'id' => 'evt-estorno', 'data' => ['id' => 'FAKE-'.$order->id]]);

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertNotFound();
    }

    public function test_late_approval_of_a_cancelled_order_is_refunded_automatically(): void
    {
        Mail::fake();
        [$gallery, $photos] = $this->makeGallery(1, withOriginals: true);
        $buyer = User::factory()->create(['cpf' => '52998224725']);
        $order = app(PhotoOrderService::class)->createFromSelection($buyer, $gallery, [$photos[0]->id]);
        $this->actingAs($buyer)->postJson(route('photos.checkout.pay', $order->reference), ['method' => 'pix', 'document' => '52998224725'])->assertOk();

        // Pix venceu (conciliação), mas o pagamento cai depois.
        $order->update(['status' => Order::STATUS_CANCELLED]);
        app(WebhookService::class)->handleMercadoPago(['type' => 'payment', 'id' => 'evt-tarde', 'data' => ['id' => 'FAKE-PIX-'.$order->id]]);

        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
        $this->assertSame(['FAKE-PIX-'.$order->id], $this->gateway->refunds);
        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertNotFound();
    }

    public function test_guest_buys_with_email_code_and_gets_a_light_account(): void
    {
        [$gallery, $photos] = $this->makeGallery(2);
        $code = app(EmailVerificationCodeService::class)->issue('mae@fotos.test');

        $response = $this->post(route('photos.checkout.start', $gallery->event->slug), [
            'photos' => $photos->pluck('id')->all(),
            'guest' => ['name' => 'Maria Mãe', 'email' => 'mae@fotos.test', 'cpf' => '529.982.247-25', 'code' => $code],
        ]);

        $order = Order::photoOrders()->firstOrFail();
        $response->assertRedirect(route('photos.checkout', $order->reference));
        $this->assertAuthenticatedAs(User::where('email', 'mae@fotos.test')->firstOrFail(), 'web');
        $this->assertSame(2 * 3500, $order->total_cents);
    }

    public function test_photo_without_original_shows_a_clear_notice_instead_of_a_file(): void
    {
        [$gallery, $photos] = $this->makeGallery(1); // demonstração: original_ref nulo
        $buyer = User::factory()->create();
        $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $this->actingAs($buyer)
            ->from(route('photos.mine'))
            ->get(route('photos.mine.download', $photos[0]))
            ->assertRedirect(route('photos.mine'))
            ->assertSessionHas('warning');

        $this->actingAs($buyer)->get(route('photos.mine'))
            ->assertInertia(fn ($page) => $page->where('galleries.0.photos.0.available', false));
    }

    public function test_original_path_cannot_escape_the_originals_folder(): void
    {
        [$gallery, $photos] = $this->makeGallery(1);
        $photos[0]->update(['original_ref' => '../../../../etc/passwd']);
        $buyer = User::factory()->create();
        $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $this->actingAs($buyer)->get(route('photos.mine.download', $photos[0]))->assertRedirect();
    }

    public function test_photo_order_cannot_be_self_refunded_by_the_buyer(): void
    {
        [$gallery, $photos] = $this->makeGallery(1);
        $buyer = User::factory()->create();
        $order = $this->paidOrder($buyer, $gallery, [$photos[0]->id]);

        $this->actingAs($buyer)->postJson(route('orders.refund', $order))->assertStatus(422);
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }

    /**
     * Galeria publicada com faixas 1 → R$ 35 e 10 → R$ 20.
     *
     * @return array{0: PhotoGallery, 1: Collection<int, Photo>}
     */
    private function makeGallery(int $count, bool $withOriginals = false): array
    {
        $event = $this->makeSession(1)->event;
        $gallery = PhotoGallery::create([
            'event_id' => $event->id,
            'status' => 'published',
            'price_tiers' => [
                ['min_qty' => 10, 'unit_price_cents' => 2000],
                ['min_qty' => 1, 'unit_price_cents' => 3500],
            ],
            'published_at' => now(),
        ]);

        $photos = collect(range(1, $count))->map(function (int $i) use ($gallery, $withOriginals): Photo {
            $photo = Photo::create([
                'photo_gallery_id' => $gallery->id,
                'filename' => sprintf('PALCO_%04d.jpg', $i),
                'preview_path' => "fotos/{$gallery->id}/{$i}-preview.webp",
                'thumb_path' => "fotos/{$gallery->id}/{$i}-thumb.webp",
                'width' => 400,
                'height' => 300,
                'position' => $i,
            ]);

            if ($withOriginals) {
                $ref = "{$gallery->id}/PALCO_{$i}.jpg";
                File::ensureDirectoryExists($this->originals.'/'.$gallery->id);
                file_put_contents($this->originals.'/'.$ref, 'original-'.$photo->id);
                $photo->update(['original_ref' => $ref]);
            }

            return $photo;
        });

        return [$gallery->load('event'), $photos];
    }

    /** @param  array<int, int>  $photoIds */
    private function paidOrder(User $user, PhotoGallery $gallery, array $photoIds): Order
    {
        $order = app(PhotoOrderService::class)->createFromSelection($user, $gallery, $photoIds);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'mercadopago',
            'method' => Payment::METHOD_CARD,
            'status' => Payment::STATUS_APPROVED,
            'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'FAKE-'.$order->id,
        ]);
        app(PhotoOrderService::class)->fulfill($order);

        return $order->refresh();
    }
}
