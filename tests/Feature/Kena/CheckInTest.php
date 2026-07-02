<?php

namespace Tests\Feature\Kena;

use App\Models\CheckIn;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_first_scan_allows_and_second_denies(): void
    {
        $session = $this->makeSession(1, 4500);
        $buyer = User::factory()->create();
        $operator = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        $reservation = app(SeatReservationService::class)
            ->hold($session, $buyer, $this->availableSeatIds($session, 1));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);
        $ticket = $order->tickets()->firstOrFail();

        $service = app(CheckInService::class);

        $first = $service->check($ticket->qr_token, $session, $operator);
        $this->assertSame(CheckIn::RESULT_OK, $first['result']);
        $this->assertSame(1, $first['progress']['checkedIn']);

        $second = $service->check($ticket->qr_token, $session, $operator);
        $this->assertSame(CheckIn::RESULT_DENIED, $second['result']);

        $invalid = $service->check('token.invalido.zz', $session, $operator);
        $this->assertSame(CheckIn::RESULT_DENIED, $invalid['result']);
        $this->assertSame('QR inválido.', $invalid['reason']);

        $this->assertSame(3, CheckIn::count());
    }
}
