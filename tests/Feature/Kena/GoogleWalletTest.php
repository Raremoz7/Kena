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

    /** @return array<string, mixed> */
    private function passObject(Ticket $ticket): array
    {
        $url = app(GoogleWalletPass::class)->saveUrl($ticket);
        $claims = explode('.', substr($url, strlen('https://pay.google.com/gp/v/save/')))[1];

        return json_decode($this->b64urlDecode($claims), true)['payload']['genericObjects'][0];
    }

    public function test_pass_has_brand_logo_card_modules_and_links(): void
    {
        $this->configureWallet();
        $ticket = $this->issuedTicket();
        $ticket->session->update(['doors_at' => $ticket->session->starts_at->copy()->subMinutes(30)]);

        $object = $this->passObject($ticket->fresh());

        $this->assertSame('#6e0021', $object['hexBackgroundColor']);
        $this->assertStringEndsWith('/img/wallet/kena-logo-660.png', $object['logo']['sourceUri']['uri']);
        $this->assertFileExists(public_path('img/wallet/kena-logo-660.png'));

        // Ids usados pelo layout da frente do cartão (classTemplateInfo da classe).
        $modules = collect($object['textModulesData'])->keyBy('id');
        $this->assertTrue($modules->has('seat'));
        $this->assertSame($ticket->session->doors_at->format('H\\hi'), $modules['doors']['body']);
        $this->assertTrue($modules->has('holder'));

        $links = collect($object['linksModuleData']['uris'])->keyBy('id');
        $this->assertSame(route('tickets.index'), $links['tickets']['uri']);
    }

    public function test_hero_image_only_when_event_has_one(): void
    {
        $this->configureWallet();
        $ticket = $this->issuedTicket();

        $this->assertArrayNotHasKey('heroImage', $this->passObject($ticket));

        $ticket->session->event->update(['slug' => 'o-quebra-nozes']);
        $object = $this->passObject($ticket->fresh());

        $this->assertStringEndsWith('/img/wallet/o-quebra-nozes-hero.jpg', $object['heroImage']['sourceUri']['uri']);
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
