<?php

namespace Tests\Feature\Kena;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/**
 * Bug de produção: o MP devolve o vencimento do Pix no fuso dele (-04:00) e o
 * Kena gravava só o "relógio" — o vencimento caía horas no passado, a reserva
 * era "estendida" para trás e o checkout fechava com "reserva expirada".
 */
class PixExpirationTimezoneTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function fakeMercadoPagoPix(): void
    {
        config(['kena.mercadopago.access_token' => 'TEST-token']);
        $this->app->bind(PaymentGateway::class, MercadoPagoGateway::class);

        // Exatamente como o MP responde: 30 min à frente, no fuso -04:00.
        $expiresMpZone = now()->addMinutes(30)->setTimezone('-04:00')->format('Y-m-d\TH:i:s.vP');

        Http::fake(['*/v1/payments' => Http::response([
            'id' => 184950899327,
            'status' => 'pending',
            'status_detail' => 'pending_waiting_transfer',
            'payment_type_id' => 'bank_transfer',
            'date_of_expiration' => $expiresMpZone,
            'point_of_interaction' => ['transaction_data' => ['qr_code' => '000201...', 'qr_code_base64' => 'aGVsbG8=']],
        ], 201)]);
    }

    public function test_pix_from_mercado_pago_keeps_the_reservation_alive(): void
    {
        $this->fakeMercadoPagoPix();
        $session = $this->makeSession(1);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));

        $response = $this->actingAs($user)->postJson(route('checkout.pay', $reservation), [
            'method' => 'pix',
            'document' => '52998224725',
        ])->assertOk();

        $response->assertJsonPath('status', 'pending');
        $this->assertNotNull($response->json('pix.copyPaste'));

        // O prazo devolvido ao front está no futuro (antes vinha horas no passado).
        $deadline = Carbon::parse($response->json('reservationExpiresAt'));
        $this->assertTrue($deadline->isFuture(), 'reserva não pode vencer ao gerar o Pix');
        $this->assertEqualsWithDelta(35 * 60, now()->diffInSeconds($deadline), 5); // Pix 30 min + 5 de folga

        $reservation->refresh();
        $this->assertSame(Reservation::STATUS_ACTIVE, $reservation->status);
        $this->assertTrue($reservation->isActive());
        $this->assertTrue(SessionSeat::where('held_by_reservation_id', $reservation->id)->first()->hold_expires_at->isFuture());

        // O vencimento gravado é o instante certo (30 min à frente).
        $pix = Payment::where('method', Payment::METHOD_PIX)->firstOrFail();
        $this->assertEqualsWithDelta(30 * 60, now()->diffInSeconds($pix->pix_expires_at), 5);
    }

    public function test_extension_never_shortens_the_reservation(): void
    {
        config(['kena.mercadopago.access_token' => 'TEST-token']);
        $this->app->bind(PaymentGateway::class, MercadoPagoGateway::class);
        // Data de vencimento no passado (resposta estranha do gateway).
        Http::fake(['*/v1/payments' => Http::response([
            'id' => 1, 'status' => 'pending', 'payment_type_id' => 'bank_transfer',
            'date_of_expiration' => now()->subHours(3)->toIso8601String(),
            'point_of_interaction' => ['transaction_data' => ['qr_code' => 'x', 'qr_code_base64' => 'eA==']],
        ], 201)]);

        $session = $this->makeSession(1);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
        $before = $reservation->expires_at->copy();

        $this->actingAs($user)->postJson(route('checkout.pay', $reservation), ['method' => 'pix', 'document' => '52998224725'])
            ->assertOk();

        $this->assertSame($before->timestamp, $reservation->refresh()->expires_at->timestamp);
        $this->assertTrue($reservation->isActive());
    }
}
