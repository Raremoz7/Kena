<?php

namespace Tests\Feature\Kena;

use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\GoogleWalletPass;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class GoogleWalletTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private string $publicKey = '';

    private function configureWallet(): void
    {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $privatePem);
        $this->publicKey = (string) openssl_pkey_get_details($res)['key'];

        Setting::put('gw_issuer_id', '3388000000000000000');
        Setting::put('gw_class_id', 'kena_evento');
        Setting::put('gw_sa_email', 'kena@projeto.iam.gserviceaccount.com');
        Setting::put('gw_private_key', $privatePem);
    }

    private function issuedTicket(): Ticket
    {
        Mail::fake();
        $session = $this->makeSession(1);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->tickets()->firstOrFail();
    }

    private function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    public function test_save_url_is_a_valid_signed_jwt(): void
    {
        $this->configureWallet();
        $ticket = $this->issuedTicket();

        $url = app(GoogleWalletPass::class)->saveUrl($ticket);

        $this->assertStringStartsWith('https://pay.google.com/gp/v/save/', $url);
        $jwt = substr($url, strlen('https://pay.google.com/gp/v/save/'));
        [$header, $claims, $signature] = explode('.', $jwt);

        // Assinatura confere com a chave pública.
        $ok = openssl_verify("{$header}.{$claims}", $this->b64urlDecode($signature), $this->publicKey, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $ok);

        // Payload traz o objeto com o QR do ingresso.
        $decoded = json_decode($this->b64urlDecode($claims), true);
        $object = $decoded['payload']['genericObjects'][0];
        $this->assertSame($ticket->qr_token, $object['barcode']['value']);
        $this->assertStringContainsString('kena_evento', $object['classId']);
    }

    public function test_ticket_route_redirects_when_configured(): void
    {
        $this->configureWallet();
        $ticket = $this->issuedTicket();

        $this->actingAs($ticket->user)
            ->get(route('tickets.google-wallet', $ticket))
            ->assertRedirect();
    }

    public function test_route_errors_when_not_configured(): void
    {
        $ticket = $this->issuedTicket();

        $this->actingAs($ticket->user)
            ->get(route('tickets.google-wallet', $ticket))
            ->assertSessionHasErrors('wallet');
    }

    public function test_other_user_cannot_get_wallet_link(): void
    {
        $this->configureWallet();
        $ticket = $this->issuedTicket();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('tickets.google-wallet', $ticket))
            ->assertForbidden();
    }
}
