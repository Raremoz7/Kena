<?php

namespace Tests\Feature\Kena;

use App\Models\SessionSeat;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** "Preciso de mais tempo" no checkout (WCAG 2.2.1): estende a reserva uma única vez. */
class ReservationExtensionTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private function holdFor(User $user)
    {
        $session = $this->makeSession(2);

        return app(SeatReservationService::class)->hold($session, $user, $this->availableSeatIds($session, 1));
    }

    public function test_owner_gets_more_time_once(): void
    {
        $user = User::factory()->create();
        $reservation = $this->holdFor($user);
        $before = $reservation->expires_at->copy();

        $res = $this->actingAs($user)->postJson(route('checkout.extend', $reservation))->assertOk();

        $expected = $before->copy()->addMinutes(SeatReservationService::EXTENSION_MINUTES);
        $this->assertSame($expected->toIso8601String(), $res->json('reservationExpiresAt'));
        $this->assertSame($expected->timestamp, $reservation->refresh()->expires_at->timestamp);
        // O lugar segurado acompanha o novo prazo.
        $this->assertSame($expected->timestamp, SessionSeat::where('held_by_reservation_id', $reservation->id)->first()->hold_expires_at->timestamp);

        $this->actingAs($user)->postJson(route('checkout.extend', $reservation))->assertStatus(422);
        $this->assertSame($expected->timestamp, $reservation->refresh()->expires_at->timestamp);
    }

    public function test_other_buyer_cannot_extend(): void
    {
        $reservation = $this->holdFor(User::factory()->create());

        $this->actingAs(User::factory()->create())->postJson(route('checkout.extend', $reservation))->assertForbidden();
    }

    public function test_expired_reservation_cannot_be_extended(): void
    {
        $user = User::factory()->create();
        $reservation = $this->holdFor($user);
        $this->travel(app(SeatReservationService::class)->holdMinutes() + 1)->minutes();

        $this->actingAs($user)->postJson(route('checkout.extend', $reservation))->assertStatus(422);
    }

    public function test_reservation_already_extended_by_pix_is_not_extended_again(): void
    {
        $this->app->instance(PaymentGateway::class, new FakeGateway);
        $user = User::factory()->create();
        $reservation = $this->holdFor($user);
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'document' => '52998224725']);

        $this->actingAs($user)->postJson(route('checkout.extend', $reservation->refresh()))->assertStatus(422);
    }

    public function test_checkout_page_exposes_the_extend_url(): void
    {
        $user = User::factory()->create();
        $reservation = $this->holdFor($user);

        $this->actingAs($user)->get(route('checkout', $reservation))
            ->assertInertia(fn ($page) => $page->where('extendUrl', route('checkout.extend', $reservation)));
    }
}
