<?php

namespace Tests\Feature\Kena;

use App\Exceptions\SeatLimitException;
use App\Models\Coupon;
use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SeatReservationService;
use App\Services\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Limite de ingressos por CPF: ninguém segura a plateia inteira. */
class SeatLimitTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kena.max_tickets_per_buyer' => 3]);
    }

    /** @param array<int, int> $seatIds */
    private function reserve(EventSession $session, array $seatIds): TestResponse
    {
        $session->loadMissing('event');

        return $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $seatIds],
        );
    }

    private function buy(EventSession $session, User $user, int $count): void
    {
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, $count));
        $order = app(OrderService::class)->createFromReservation($reservation);
        app(TicketIssuanceService::class)->issueForOrder($order);
    }

    public function test_reserving_more_than_the_limit_is_rejected(): void
    {
        $session = $this->makeSession(5);
        $this->actingAs(User::factory()->create());

        $this->reserve($session, $this->availableSeatIds($session, 4))->assertSessionHasErrors('seats');

        $this->assertSame(0, Reservation::count());
        $this->assertSame(5, SessionSeat::where('status', SessionSeat::STATUS_AVAILABLE)->count());
    }

    public function test_reserving_up_to_the_limit_works(): void
    {
        $session = $this->makeSession(5);
        $this->actingAs(User::factory()->create());

        $this->reserve($session, $this->availableSeatIds($session, 3))->assertSessionHasNoErrors();

        $this->assertSame(3, SessionSeat::where('status', SessionSeat::STATUS_HELD)->count());
    }

    public function test_tickets_already_bought_count_against_the_limit(): void
    {
        $session = $this->makeSession(6);
        $user = User::factory()->create();
        $this->buy($session, $user, 2);
        $this->actingAs($user);

        $this->reserve($session, $this->availableSeatIds($session, 2))
            ->assertSessionHasErrors(['seats' => 'Cada CPF pode levar até 3 ingressos por sessão. Você ainda pode escolher 1.']);

        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasNoErrors();
    }

    public function test_service_enforces_the_limit_even_without_the_controller(): void
    {
        $session = $this->makeSession(5);

        $this->expectException(SeatLimitException::class);
        app(SeatReservationService::class)->hold($session, User::factory()->create(), $this->availableSeatIds($session, 4));
    }

    public function test_seats_page_tells_the_front_how_many_seats_remain(): void
    {
        $session = $this->makeSession(6);
        $session->load('event');
        $user = User::factory()->create();
        $this->buy($session, $user, 2);
        $url = route('sessions.seats', ['slug' => $session->event->slug, 'session' => $session->id]);

        $this->get($url)->assertInertia(fn ($page) => $page->where('maxSeats', 3));
        $this->actingAs($user)->get($url)->assertInertia(fn ($page) => $page->where('maxSeats', 1));
    }

    public function test_courtesy_tickets_count_against_the_limit(): void
    {
        $session = $this->makeSession(6);
        $user = User::factory()->create();
        $coupon = Coupon::create(['event_id' => $session->event_id, 'code' => 'ANA7Q2', 'type' => Coupon::TYPE_TICKETS, 'value' => 2, 'max_uses' => 1, 'used' => 0, 'active' => true]);
        $reservation = app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 2));
        $order = app(OrderService::class)->createFromReservation($reservation, $coupon);
        app(TicketIssuanceService::class)->issueForOrder($order);

        $this->assertSame(1, app(SeatReservationService::class)->remainingFor($session, $user));
    }

    public function test_account_without_cpf_must_inform_it_to_reserve(): void
    {
        $session = $this->makeSession(3);
        $user = User::factory()->withoutCpf()->create();
        $this->actingAs($user);

        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasErrors('cpf');
        $this->assertSame(0, Reservation::count());

        $session->loadMissing('event');
        $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $this->availableSeatIds($session, 1), 'cpf' => '529.982.247-25'],
        )->assertSessionHasNoErrors();
        $this->assertSame('52998224725', $user->fresh()?->cpf);
    }

    public function test_cpf_from_another_account_cannot_be_reused_to_double_the_limit(): void
    {
        $session = $this->makeSession(3);
        $session->load('event');
        User::factory()->create(['cpf' => '52998224725']);
        $this->actingAs(User::factory()->withoutCpf()->create());

        $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $this->availableSeatIds($session, 1), 'cpf' => '52998224725'],
        )->assertSessionHasErrors('cpf');
        $this->assertSame(0, Reservation::count());
    }

    public function test_guest_over_the_limit_is_rejected_before_creating_an_account(): void
    {
        $session = $this->makeSession(5);
        $session->load('event');

        $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            [
                'seats' => $this->availableSeatIds($session, 4),
                'guest' => ['name' => 'Robô', 'email' => 'bot@guest.test', 'cpf' => '52998224725'],
            ],
        )->assertSessionHasErrors('seats');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'bot@guest.test']);
    }
}
