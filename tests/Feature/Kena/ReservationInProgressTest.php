<?php

namespace Tests\Feature\Kena;

use App\Models\EventSession;
use App\Models\Reservation;
use App\Models\SessionSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Uma reserva por vez: com uma em andamento, o comprador retoma ou cancela. */
class ReservationInProgressTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    /** @param array<int, int> $seatIds */
    private function reserve(EventSession $session, array $seatIds): TestResponse
    {
        $session->loadMissing('event');

        return $this->post(
            route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]),
            ['seats' => $seatIds],
        );
    }

    public function test_cannot_start_another_reservation_while_one_is_in_progress(): void
    {
        $session = $this->makeSession(4);
        $other = $this->makeSession(4);
        $this->actingAs(User::factory()->create());

        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasNoErrors();

        // Nem na mesma sessão, nem em outra.
        $this->reserve($session, $this->availableSeatIds($session, 1))
            ->assertSessionHasErrors(['seats' => 'Você já tem uma reserva em andamento. Retome o pagamento ou cancele a reserva antes de escolher outros assentos.']);
        $this->reserve($other, $this->availableSeatIds($other, 1))->assertSessionHasErrors('seats');

        $this->assertSame(1, Reservation::where('status', Reservation::STATUS_ACTIVE)->count());
        $this->assertSame(1, SessionSeat::where('status', SessionSeat::STATUS_HELD)->count());
    }

    public function test_expired_reservation_does_not_block(): void
    {
        $session = $this->makeSession(4);
        $this->actingAs(User::factory()->create());

        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasNoErrors();
        $this->travel(config('kena.hold_minutes', 10) + 1)->minutes();

        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasNoErrors();
    }

    public function test_active_reservation_is_shared_with_every_buyer_page(): void
    {
        $session = $this->makeSession(4);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->reserve($session, $this->availableSeatIds($session, 2));
        $reservation = Reservation::firstOrFail();

        $this->get(route('events.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeReservation.id', $reservation->id)
            ->where('activeReservation.checkoutUrl', route('checkout', $reservation, false))
            ->has('activeReservation.seats', 2)
            ->etc());
    }

    public function test_no_active_reservation_for_guests_or_after_cancelling(): void
    {
        $session = $this->makeSession(4);

        $this->get(route('events.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeReservation', null)
            ->etc());

        $this->actingAs(User::factory()->create());
        $this->reserve($session, $this->availableSeatIds($session, 1));
        $reservation = Reservation::firstOrFail();

        // Cancelar pelo aviso flutuante fica na página em que o comprador estava.
        $this->from(route('events.index'))
            ->delete(route('reservations.release', ['reservation' => $reservation, 'voltar' => 1]))
            ->assertRedirect(route('events.index'));

        $this->assertSame(Reservation::STATUS_CANCELLED, $reservation->fresh()->status);
        $this->assertSame(0, SessionSeat::where('status', SessionSeat::STATUS_HELD)->count());
        $this->get(route('events.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeReservation', null)
            ->etc());

        // E aí pode reservar de novo.
        $this->reserve($session, $this->availableSeatIds($session, 1))->assertSessionHasNoErrors();
    }
}
