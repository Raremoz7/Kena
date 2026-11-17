<?php

namespace Tests\Feature\Kena;

use App\Mail\NewOrganizerSignupMail;
use App\Mail\OrganizationStatusMail;
use App\Mail\PanelPasswordResetMail;
use App\Mail\TicketsIssuedMail;
use App\Models\MercadoPagoAccount;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PanelUser;
use App\Models\Payment;
use App\Models\PhotoGallery;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Photos\PhotoPricing;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Support\MercadoPagoSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Fechamento dos fluxos: vendas antigas, senha, avisos, contato e taxa das fotos. */
class PendingFlowsTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function order(): Order
    {
        $session = $this->makeSession(1);
        $reservation = app(SeatReservationService::class)
            ->hold($session, User::factory()->create(), $this->availableSeatIds($session, 1));

        return app(OrderService::class)->createFromReservation($reservation);
    }

    // --- Vendas de antes do OAuth ---

    public function test_payment_made_before_oauth_keeps_using_the_platform_credential(): void
    {
        Setting::put('mp_access_token', 'APP_USR-PLATAFORMA');
        $order = $this->order();
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'method' => 'card',
            'status' => 'approved', 'amount_cents' => $order->total_cents, 'gateway_payment_id' => '900',
        ]);

        config(['kena.mercadopago.oauth.enabled' => true]);
        $account = MercadoPagoAccount::create([
            'organization_id' => Organization::DEFAULT_ID, 'mp_user_id' => '1', 'access_token' => 'APP_USR-ORGANIZADOR',
            'connected_at' => now(), 'expires_at' => now()->addDays(100),
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'mercadopago', 'mercadopago_account_id' => $account->id, 'method' => 'pix',
            'status' => 'approved', 'amount_cents' => $order->total_cents, 'gateway_payment_id' => '901',
        ]);

        // Venda antiga: estorno/consulta na conta que recebeu (a da plataforma).
        $this->assertSame('APP_USR-PLATAFORMA', MercadoPagoSettings::accessTokenForPayment('900'));
        // Venda nova: na conta do organizador.
        $this->assertSame('APP_USR-ORGANIZADOR', MercadoPagoSettings::accessTokenForPayment('901'));
        $this->assertSame('APP_USR-ORGANIZADOR', MercadoPagoSettings::accessToken());
    }

    // --- Esqueci minha senha ---

    public function test_panel_password_reset_flow(): void
    {
        Mail::fake();
        $user = PanelUser::factory()->create(['email' => 'ana@teatro.test']);

        $this->post('/painel/esqueci-senha', ['email' => 'ana@teatro.test'])->assertRedirect('/painel/login');

        $url = null;
        Mail::assertSent(PanelPasswordResetMail::class, function (PanelPasswordResetMail $m) use (&$url): bool {
            $url = $m->url;

            return $m->hasTo('ana@teatro.test');
        });
        $this->assertStringContainsString('/painel/redefinir-senha/', (string) $url);

        preg_match('#/painel/redefinir-senha/([^?]+)#', (string) $url, $m);
        $this->get((string) $url)->assertOk()->assertInertia(fn (Assert $p) => $p->component('painel/reset-password'));
        $this->post('/painel/redefinir-senha', [
            'token' => $m[1], 'email' => 'ana@teatro.test', 'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123',
        ])->assertRedirect('/painel/login');

        $this->post('/painel/login', ['email' => 'ana@teatro.test', 'password' => 'novaSenha123'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user, 'painel');
    }

    public function test_password_reset_does_not_reveal_unknown_emails_or_touch_buyers(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'comprador@x.test']);

        $this->post('/painel/esqueci-senha', ['email' => 'comprador@x.test'])
            ->assertRedirect('/painel/login')
            ->assertSessionHas('status');

        Mail::assertNothingSent();
    }

    public function test_reset_with_invalid_token_fails(): void
    {
        PanelUser::factory()->create(['email' => 'ana@teatro.test']);

        $this->post('/painel/redefinir-senha', [
            'token' => 'falso', 'email' => 'ana@teatro.test', 'password' => 'novaSenha123', 'password_confirmation' => 'novaSenha123',
        ])->assertSessionHasErrors('email');
    }

    // --- Avisos por e-mail ---

    public function test_suspension_and_reactivation_notify_the_organizers_only(): void
    {
        Mail::fake();
        $b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        PanelUser::factory()->forOrganization($b)->create(['email' => 'dono@b.test']);
        PanelUser::factory()->forOrganization($b)->staff()->create(['email' => 'portaria@b.test']);
        $as = $this->actingAs(PlatformAdmin::factory()->create(), 'admin');

        $as->post("/admin/organizacoes/{$b->id}/suspender", ['reason' => 'Denúncia']);
        Mail::assertQueued(OrganizationStatusMail::class, fn (OrganizationStatusMail $m): bool => $m->suspended
            && $m->reason === 'Denúncia' && $m->hasTo('dono@b.test') && ! $m->hasTo('portaria@b.test'));

        $as->post("/admin/organizacoes/{$b->id}/reativar");
        Mail::assertQueued(OrganizationStatusMail::class, fn (OrganizationStatusMail $m): bool => ! $m->suspended && $m->hasTo('dono@b.test'));
    }

    public function test_super_admin_is_told_about_new_signups(): void
    {
        Mail::fake();
        config(['kena.organizer_signup.enabled' => true, 'kena.mercadopago.oauth.enabled' => true]);
        PlatformAdmin::factory()->create(['email' => 'somo@somo.test']);

        $this->post('/organizador/cadastro', [
            'organization_name' => 'Cia Nova', 'name' => 'Rui', 'email' => 'rui@gmail.com',
            'password' => 'senha1234', 'password_confirmation' => 'senha1234', 'accept_terms' => '1',
        ])->assertRedirect('/painel/login');

        Mail::assertQueued(NewOrganizerSignupMail::class, fn (NewOrganizerSignupMail $m): bool => $m->hasTo('somo@somo.test')
            && $m->organizationName === 'Cia Nova' && $m->ownerEmail === 'rui@gmail.com');
    }

    // --- Contato do organizador ---

    public function test_organizer_contact_appears_on_event_and_tickets_email(): void
    {
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post('/painel/config', ['organization_email' => 'Contato@Teatro.test'])
            ->assertRedirect();
        $this->assertSame('contato@teatro.test', Organization::find(Organization::DEFAULT_ID)?->contact_email);

        $order = $this->order();
        $this->get('/e/'.$order->session?->event->slug)
            ->assertInertia(fn (Assert $p) => $p->where('event.organizer.email', 'contato@teatro.test'));

        app(TicketIssuanceService::class)->issueForOrder($order);
        (new TicketsIssuedMail($order->fresh() ?? $order))->assertSeeInHtml('contato@teatro.test');
    }

    // --- Taxa das fotos ---

    public function test_photo_fee_is_separate_from_ticket_fee(): void
    {
        config(['kena.service_fee_percent' => 6, 'kena.photo_fee_percent' => 6]);
        $session = $this->makeSession(1, 10000);
        $gallery = PhotoGallery::create([
            'event_id' => $session->event_id, 'status' => 'published',
            'price_tiers' => [['min_qty' => 1, 'unit_price_cents' => 2000]],
        ]);
        $org = Organization::findOrFail(Organization::DEFAULT_ID);

        $quote = app(PhotoPricing::class)->quote($gallery, 3);
        $this->assertSame(360, $quote['fee_cents']); // 6% de 6000
        $this->assertSame(6360, $quote['total_cents']);

        // Muda só a das fotos: o ingresso continua com a dele.
        $admin = $this->actingAs(PlatformAdmin::factory()->create(), 'admin');
        $admin->put("/admin/organizacoes/{$org->id}/taxa", ['photo_fee_percent' => 10]);
        $this->assertSame(10.0, $org->fresh()?->photoFeePercent());
        $this->assertSame(6.0, $org->fresh()?->serviceFeePercent());
        $this->assertSame(600, app(PhotoPricing::class)->quote($gallery->fresh() ?? $gallery, 3)['fee_cents']);

        // E vice-versa.
        $admin->put("/admin/organizacoes/{$org->id}/taxa", ['fee_percent' => 4]);
        $this->assertSame(4.0, $org->fresh()?->serviceFeePercent());
        $this->assertSame(10.0, $org->fresh()?->photoFeePercent());

        // Zero desliga a taxa das fotos.
        $admin->put("/admin/organizacoes/{$org->id}/taxa", ['photo_fee_percent' => 0]);
        $this->assertSame(0, app(PhotoPricing::class)->quote($gallery->fresh() ?? $gallery, 3)['fee_cents']);
    }
}
