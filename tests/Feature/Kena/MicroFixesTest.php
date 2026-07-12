<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\OrderService;
use App\Services\Payments\PaymentGateway;
use App\Services\QrTokenService;
use App\Services\SeatReservationService;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Micro-correções: parsing do QR e reprocessamento de webhook sem payment. */
class MicroFixesTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_code_from_token_rejects_malformed_token(): void
    {
        $qr = app(QrTokenService::class);

        $this->assertNull($qr->codeFromToken('lixo-sem-pontos'));
        $this->assertNull($qr->codeFromToken('so.dois'));
        $this->assertSame('KNA-ABC', $qr->codeFromToken('KNA-ABC.nonce.sig'));
    }

    public function test_webhook_without_payment_is_not_marked_processed(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);

        // Webhook chega ANTES do Payment existir (aprovação instantânea durante o pay()).
        $payload = ['type' => 'payment', 'id' => 'evt-early', 'data' => ['id' => 'MP-LATE']];
        app(WebhookService::class)->handleMercadoPago($payload);

        $record = WebhookEvent::where('gateway_event_id', 'evt-early')->firstOrFail();
        $this->assertNull($record->processed_at, 'sem payment ainda, o evento deve poder reprocessar');

        // Agora o payment existe (pay() terminou); a reentrega do MP processa.
        $session = $this->makeSession(1, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'method' => Payment::METHOD_PIX,
            'status' => Payment::STATUS_PENDING, 'amount_cents' => $order->total_cents,
            'gateway_payment_id' => 'MP-LATE',
        ]);

        app(WebhookService::class)->handleMercadoPago($payload);

        $this->assertNotNull($record->refresh()->processed_at, 'com payment, processa e marca');
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }
}
