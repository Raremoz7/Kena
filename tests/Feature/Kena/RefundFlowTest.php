<?php

namespace Tests\Feature\Kena;

use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class RefundFlowTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function paidOrder(?CarbonInterface $startsAt = null): Order
    {
        Mail::fake();
        $session = $this->makeSession(2);
        if ($startsAt !== null) {
            $session->update(['starts_at' => $startsAt]);
        }
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 2));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);

        return $order->refresh();
    }

    public function test_buyer_can_refund_within_deadline(): void
    {
        $order = $this->paidOrder(); // sessão em +30d → dentro do prazo

        $this->actingAs($order->user)
            ->postJson(route('orders.refund', $order))
            ->assertOk();

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::first()->status);
        Mail::assertQueued(RefundConfirmedMail::class);
    }

    public function test_buyer_cannot_refund_past_deadline(): void
    {
        $order = $this->paidOrder(now()->addHour()); // sessão em 1h → prazo encerrado

        $this->actingAs($order->user)
            ->postJson(route('orders.refund', $order))
            ->assertStatus(422);

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }

    public function test_organizer_can_refund_anytime(): void
    {
        $order = $this->paidOrder(now()->addHour()); // prazo do comprador encerrado
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $this->actingAs($organizer)
            ->post(route('admin.orders.refund', $order))
            ->assertRedirect();

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
    }
}
