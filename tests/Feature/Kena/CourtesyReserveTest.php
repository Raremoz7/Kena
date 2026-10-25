<?php

namespace Tests\Feature\Kena;

use App\Exceptions\CourtesyRequiredException;
use App\Models\Coupon;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use App\Models\TicketLot;
use App\Models\User;
use App\Services\CouponService;
use App\Services\CourtesyReserveService;
use App\Services\CourtesyService;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Cota de cortesia: só a quantidade fica guardada, o pai escolhe o lugar e usa o código no pagamento. */
class CourtesyReserveTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(PaymentGateway::class, new FakeGateway);
    }

    /** Sessão com 6 lugares e 2 guardados para cortesia: 4 pagos à venda. */
    private function sessionWithReserve(int $seats = 6, int $reserve = 2): EventSession
    {
        $session = $this->makeSession($seats);
        $session->update(['courtesy_reserve' => $reserve]);

        return $session->fresh();
    }

    private function hold(EventSession $session, int $seats, ?User $user = null): Reservation
    {
        return app(SeatReservationService::class)
            ->hold($session, $user ?? User::factory()->create(), $this->availableSeatIds($session, $seats));
    }

    private function courtesy(EventSession $session, string $name, int $tickets = 2): Coupon
    {
        return app(CourtesyService::class)->generate($session->event, [['name' => $name, 'group' => null]], $tickets, null)->first();
    }

    private function requiredCount(Reservation $reservation): int
    {
        return ReservationSeat::where('reservation_id', $reservation->id)->where('requires_courtesy', true)->count();
    }

    public function test_paid_sales_stop_when_only_the_reserve_is_left(): void
    {
        $session = $this->sessionWithReserve();

        $paid = $this->hold($session, 4);
        $this->assertSame(0, $this->requiredCount($paid));
        $this->assertSame(0, app(CourtesyReserveService::class)->paidLeft($session));

        // Os 2 lugares que sobram ainda podem ser escolhidos, mas só fecham com cortesia.
        $late = $this->hold($session, 2);
        $this->assertSame(2, $this->requiredCount($late));

        $this->expectException(CourtesyRequiredException::class);
        app(PaymentService::class)->pay($late, ['method' => 'pix']);
    }

    public function test_parent_picks_any_free_seat_and_closes_with_the_courtesy_code(): void
    {
        $session = $this->sessionWithReserve();
        $this->hold($session, 4);
        $coupon = $this->courtesy($session, 'Maria Silva');

        $reservation = $this->hold($session, 2);
        $order = app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => $coupon->code]);

        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(0, $order->total_cents);
        $this->assertSame(2, app(CourtesyReserveService::class)->courtesyUsed($session));
    }

    public function test_courtesy_only_covers_its_tickets_the_rest_is_still_rejected(): void
    {
        $session = $this->sessionWithReserve(6, 3);
        $this->hold($session, 3);
        $coupon = $this->courtesy($session, 'Ana', tickets: 2);

        // 3 pagos já vendidos, sobram 3 lugares da cota; cortesia de 2 não cobre os 3.
        $reservation = $this->hold($session, 3);
        $this->assertSame(3, $this->requiredCount($reservation));

        $this->expectException(CourtesyRequiredException::class);
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => $coupon->code]);
    }

    public function test_hold_mixes_paid_and_courtesy_when_it_crosses_the_limit(): void
    {
        $session = $this->sessionWithReserve();
        $this->hold($session, 3);
        $coupon = $this->courtesy($session, 'Bia', tickets: 1);

        // 1 pago ainda cabe; o 2º lugar é da cota.
        $reservation = $this->hold($session, 2);
        $this->assertSame(1, $this->requiredCount($reservation));

        $order = app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => $coupon->code]);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(4500, $order->subtotal_cents - $order->discount_cents);
    }

    public function test_courtesy_used_early_frees_a_paid_seat(): void
    {
        $session = $this->sessionWithReserve();
        $coupon = $this->courtesy($session, 'Carla');

        // Pai com cortesia chega antes da venda esgotar: o lugar não come a venda paga.
        $reservation = $this->hold($session, 2);
        app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => $coupon->code]);

        $this->assertSame(4, app(CourtesyReserveService::class)->paidLeft($session));
    }

    public function test_code_typed_with_spaces_or_accents_still_works(): void
    {
        $session = $this->makeSession(2);
        $coupon = $this->courtesy($session, 'João Pedro');
        $suffix = substr($coupon->code, -3);

        $this->assertSame($coupon->id, app(CouponService::class)->resolve('joão pedro '.strtolower($suffix), $session->event_id)?->id);
    }

    public function test_reserve_is_ignored_when_zero(): void
    {
        $session = $this->makeSession(3);

        $reservation = $this->hold($session, 3);

        $this->assertSame(0, $this->requiredCount($reservation));
        $this->assertNull(app(CourtesyReserveService::class)->paidLeft($session));
    }

    public function test_sold_out_lots_do_not_block_courtesy_seats(): void
    {
        $session = $this->sessionWithReserve();
        TicketLot::create(['session_id' => $session->id, 'name' => 'Único', 'price_cents' => 5000, 'quantity' => 4, 'position' => 0]);
        $this->hold($session, 4);

        $reservation = $this->hold($session, 2);

        $this->assertSame(2, $this->requiredCount($reservation));
        $this->assertSame(5000, (int) ReservationSeat::where('reservation_id', $reservation->id)->value('price_cents'));
    }

    public function test_organizer_sets_the_reserve_on_the_lots_page(): void
    {
        $session = $this->makeSession(6);
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')
            ->put(route('admin.lots.reserve', $session), ['courtesy_reserve' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $session->fresh()->courtesy_reserve);

        $this->actingAs($organizer, 'painel')
            ->put(route('admin.lots.reserve', $session), ['courtesy_reserve' => 7])
            ->assertSessionHasErrors('courtesy_reserve');

        $this->actingAs($organizer, 'painel')->get(route('admin.lots', $session))->assertOk();
    }

    public function test_checkout_tells_the_parent_a_code_is_needed(): void
    {
        $session = $this->sessionWithReserve();
        $this->hold($session, 4);
        $parent = User::factory()->create();
        $reservation = $this->hold($session, 2, $parent);

        $this->actingAs($parent)
            ->get(route('checkout', $reservation))
            ->assertInertia(fn ($page) => $page->where('courtesyRequired', 2));

        $response = $this->actingAs($parent)
            ->postJson(route('checkout.pay', $reservation), ['method' => 'pix'])
            ->assertStatus(422);
        $this->assertStringContainsString('cota de cortesia', (string) $response->json('message'));
    }

    public function test_zero_total_confirms_without_payment_step(): void
    {
        $session = $this->makeSession(4);
        $coupon = $this->courtesy($session, 'Maria Silva');
        $parent = User::factory()->create();
        $reservation = $this->hold($session, 2, $parent);

        // Sem cupom, "confirmar sem pagar" é recusado.
        $this->actingAs($parent)
            ->postJson(route('checkout.pay', $reservation), ['method' => 'free'])
            ->assertStatus(422);

        $this->actingAs($parent)
            ->postJson(route('checkout.pay', $reservation), ['method' => 'free', 'coupon_code' => $coupon->code])
            ->assertOk()
            ->assertJsonPath('status', Order::STATUS_PAID);
    }
}
