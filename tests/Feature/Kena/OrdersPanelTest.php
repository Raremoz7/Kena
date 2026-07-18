<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\PanelUser;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class OrdersPanelTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function aPaidOrder(): Order
    {
        Mail::fake();
        $session = $this->makeSession(2);
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 2));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_organizer_sees_orders_and_exports_attendees_csv(): void
    {
        $order = $this->aPaidOrder();
        $organizer = PanelUser::factory()->create();
        $ticket = $order->tickets()->firstOrFail();

        $this->actingAs($organizer, 'painel')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/orders')->has('orders.data', 1));

        $response = $this->actingAs($organizer, 'painel')->get(route('admin.orders.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Código', $csv);
        $this->assertStringContainsString($ticket->code, $csv);
        $this->assertStringContainsString($order->user->email, $csv);
    }

    public function test_buyer_cannot_access_orders_panel(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get(route('admin.orders'))->assertRedirect(route('painel.login'));
    }
}
