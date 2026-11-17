<?php

namespace Tests\Feature\Kena;

use App\Mail\TicketsIssuedMail;
use App\Models\EventSession;
use App\Models\MercadoPagoAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Vitrine única + página por organizador. */
class MultiTenantPublicSiteTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private Organization $b;

    private EventSession $sessionB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeSession(1)->event->update(['title' => 'Espetaculo da Principal']);
        $this->b = Organization::create(['name' => 'Teatro B', 'slug' => 'teatro-b']);
        $this->sessionB = CurrentOrganization::run($this->b->id, fn (): EventSession => $this->makeSession(1));
        $this->sessionB->event->update(['title' => 'Espetaculo do B']);
    }

    public function test_catalog_lists_every_active_organization_and_hides_suspended(): void
    {
        $this->get('/eventos')->assertSee('Espetaculo da Principal')->assertSee('Espetaculo do B');

        $this->b->update(['status' => Organization::STATUS_SUSPENDED]);

        $this->get('/eventos')->assertSee('Espetaculo da Principal')->assertDontSee('Espetaculo do B');
        $this->get('/e/'.$this->sessionB->event->slug)->assertNotFound();
        $this->get('/o/teatro-b')->assertNotFound();
    }

    public function test_with_oauth_organization_without_mercado_pago_is_hidden(): void
    {
        config(['kena.mercadopago.oauth.enabled' => true]);
        MercadoPagoAccount::create([
            'organization_id' => Organization::DEFAULT_ID, 'mp_user_id' => '1', 'access_token' => 'x', 'connected_at' => now(),
        ]);

        $this->get('/eventos')->assertSee('Espetaculo da Principal')->assertDontSee('Espetaculo do B');
    }

    public function test_organizer_page_lists_only_its_events(): void
    {
        $this->get('/o/teatro-b')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('buyer/organizer')
                ->where('organization.name', 'Teatro B')
                ->has('events', 1)
                ->where('events.0.title', 'Espetaculo do B'));
    }

    public function test_event_page_shows_the_organizer(): void
    {
        $this->get('/e/'.$this->sessionB->event->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.organizer.name', 'Teatro B')
                ->where('event.organizer.url', '/o/teatro-b'));
    }

    public function test_tickets_email_names_the_organizer(): void
    {
        $reservation = app(SeatReservationService::class)
            ->hold($this->sessionB, User::factory()->create(), $this->availableSeatIds($this->sessionB, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        (new TicketsIssuedMail($order->fresh() ?? $order))->assertSeeInHtml('Organização: Teatro B');
    }
}
