<?php

namespace Tests\Feature\Kena;

use App\Exceptions\SeatConflictException;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

class SeatReservationTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_holding_seats_marks_them_held(): void
    {
        $session = $this->makeSession(3);
        $user = User::factory()->create();
        $ids = $this->availableSeatIds($session, 2);

        $reservation = app(SeatReservationService::class)->hold($session, $user, $ids);

        $this->assertSame('active', $reservation->status);
        $this->assertSame(2, SessionSeat::where('status', SessionSeat::STATUS_HELD)->count());
    }

    public function test_two_users_cannot_hold_the_same_seat(): void
    {
        $session = $this->makeSession(3);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $ids = $this->availableSeatIds($session, 1);

        $service = app(SeatReservationService::class);
        $service->hold($session, $first, $ids);

        $this->expectException(SeatConflictException::class);
        $service->hold($session, $second, $ids);
    }

    public function test_availability_snapshot_reflects_held_seat(): void
    {
        $session = $this->makeSession(2);
        $user = User::factory()->create();
        $ids = $this->availableSeatIds($session, 1);

        app(SeatReservationService::class)->hold($session, $user, $ids);

        $snapshot = app(AvailabilityService::class)->snapshot($session);

        $this->assertSame('held', $snapshot[$ids[0]]);
    }
}
