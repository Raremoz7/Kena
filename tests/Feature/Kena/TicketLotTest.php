<?php

namespace Tests\Feature\Kena;

use App\Exceptions\LotsSoldOutException;
use App\Models\Coupon;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PanelUser;
use App\Models\ReservationSeat;
use App\Models\TicketLot;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\SeatReservationService;
use App\Services\TicketLotService;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/** Lotes: preço por lote, virada automática, saldo, cortesia fora do lote e painel. */
class TicketLotTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    /** Sessão com 6 lugares: 1º lote 2 ingressos a R$ 45, 2º lote sem limite a R$ 60. */
    private function sessionWithLots(?int $secondQuantity = null): EventSession
    {
        $session = $this->makeSession(6, 4500);
        TicketLot::create(['session_id' => $session->id, 'name' => '1º lote', 'price_cents' => 4500, 'quantity' => 2, 'position' => 0]);
        TicketLot::create(['session_id' => $session->id, 'name' => '2º lote', 'price_cents' => 6000, 'quantity' => $secondQuantity, 'position' => 1]);

        return $session;
    }

    private function hold(EventSession $session, int $seats, ?User $user = null)
    {
        return app(SeatReservationService::class)
            ->hold($session, $user ?? User::factory()->create(), $this->availableSeatIds($session, $seats));
    }

    private function lots(): TicketLotService
    {
        return app(TicketLotService::class);
    }

    public function test_hold_uses_the_current_lot_price(): void
    {
        $session = $this->sessionWithLots();

        $reservation = $this->hold($session, 1);

        $rs = ReservationSeat::where('reservation_id', $reservation->id)->firstOrFail();
        $this->assertSame(4500, $rs->price_cents);
        $this->assertSame('1º lote', TicketLot::find($rs->lot_id)?->name);
    }

    public function test_next_lot_opens_by_itself_when_the_first_sells_out(): void
    {
        $session = $this->sessionWithLots();

        $this->hold($session, 2); // esgota o 1º lote
        $this->assertSame('2º lote', $this->lots()->current($session)?->name);

        $second = $this->hold($session, 1);
        $this->assertSame(6000, (int) ReservationSeat::where('reservation_id', $second->id)->value('price_cents'));
    }

    public function test_purchase_across_the_boundary_splits_prices(): void
    {
        $session = $this->sessionWithLots();
        $this->hold($session, 1); // sobra 1 no 1º lote

        $reservation = $this->hold($session, 3);

        $prices = ReservationSeat::where('reservation_id', $reservation->id)->pluck('price_cents')->sort()->values()->all();
        $this->assertSame([4500, 6000, 6000], $prices);
    }

    public function test_expired_hold_gives_the_lot_back(): void
    {
        $session = $this->sessionWithLots();
        $this->hold($session, 2);
        $this->assertSame('2º lote', $this->lots()->current($session)?->name);

        $this->travel(app(SeatReservationService::class)->holdMinutes() + 1)->minutes();

        $this->assertSame('1º lote', $this->lots()->current($session)?->name);
    }

    public function test_buyer_replacing_own_hold_does_not_burn_the_lot(): void
    {
        $session = $this->sessionWithLots();
        $user = User::factory()->create();

        $this->hold($session, 2, $user);
        $again = $this->hold($session, 2, $user); // nova seleção substitui a anterior

        $this->assertSame([4500, 4500], ReservationSeat::where('reservation_id', $again->id)->pluck('price_cents')->all());
    }

    public function test_all_lots_sold_out_blocks_the_sale(): void
    {
        $session = $this->sessionWithLots(secondQuantity: 1);
        $this->hold($session, 3); // 2 + 1: tudo vendido

        $this->expectException(LotsSoldOutException::class);
        $this->hold($session, 1);
    }

    public function test_reserve_route_explains_when_lots_are_sold_out(): void
    {
        $session = $this->sessionWithLots(secondQuantity: 1);
        $session->load('event');
        $this->hold($session, 3);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->post(route('sessions.reserve', ['slug' => $session->event->slug, 'session' => $session->id]), [
                'seats' => $this->availableSeatIds($session, 1),
            ])
            ->assertSessionHasErrors(['seats' => 'Os ingressos desta sessão esgotaram.']);
    }

    public function test_paid_order_keeps_counting_in_its_lot(): void
    {
        $session = $this->sessionWithLots();
        $reservation = $this->hold($session, 2);

        app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok']);

        $this->assertSame(Order::STATUS_PAID, Order::where('reservation_id', $reservation->id)->value('status'));
        $this->travel(60)->minutes(); // o hold venceria, mas a venda continua contando
        $this->assertSame('2º lote', $this->lots()->current($session)?->name);
        $this->assertSame([4500, 4500], OrderItem::pluck('price_cents')->all());
    }

    public function test_courtesy_tickets_are_free_and_do_not_count_in_the_lot(): void
    {
        $session = $this->sessionWithLots();
        Coupon::create([
            'event_id' => $session->event_id,
            'code' => 'QN-ANA',
            'type' => Coupon::TYPE_TICKETS,
            'value' => 2,
            'max_uses' => 1,
            'used' => 0,
            'active' => true,
        ]);
        $reservation = $this->hold($session, 2);

        $order = app(PaymentService::class)->pay($reservation, ['method' => 'pix', 'coupon_code' => 'qn-ana']);

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
        $this->assertSame(0, $order->total_cents);
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->where('courtesy', true)->count());
        // O 1º lote continua com os 2 ingressos pagos à venda.
        $this->assertSame('1º lote', $this->lots()->current($session)?->name);
        $this->assertSame(2, $this->lots()->remaining($this->lots()->current($session)));
    }

    public function test_courtesy_with_extra_ticket_charges_only_the_extra(): void
    {
        $session = $this->sessionWithLots();
        Coupon::create([
            'code' => 'QN-BIA', 'type' => Coupon::TYPE_TICKETS, 'value' => 2,
            'max_uses' => 1, 'used' => 0, 'active' => true,
        ]);
        $reservation = $this->hold($session, 3);

        $order = app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok', 'coupon_code' => 'QN-BIA']);

        $order->refresh();
        // 2 lugares no 1º lote (45 + 45) e o 3º já no 2º lote (60).
        $this->assertSame(15000, $order->subtotal_cents);
        // A cortesia zera os 2 mais caros: o pai paga só o mais barato.
        $this->assertSame(10500, $order->discount_cents);
        $this->assertSame([4500], OrderItem::where('order_id', $order->id)->where('courtesy', false)->pluck('price_cents')->all());
    }

    public function test_site_shows_current_and_next_lot(): void
    {
        $session = $this->sessionWithLots();
        $this->hold($session, 1);

        $info = CatalogPresenter::lotInfo($session);

        $this->assertSame('1º lote', $info['current']['name']);
        $this->assertSame(1, $info['current']['remaining']);
        $this->assertSame('2º lote', $info['next']['name']);
        $this->assertFalse($info['soldOut']);

        $prices = collect(CatalogPresenter::seatMap($session)['seats'])->pluck('price')->unique()->values()->all();
        $this->assertSame([45.0], array_map('floatval', $prices));
    }

    public function test_session_without_lots_keeps_sector_price(): void
    {
        $session = $this->makeSession(2, 5000);

        $reservation = $this->hold($session, 1);

        $this->assertSame(5000, (int) ReservationSeat::where('reservation_id', $reservation->id)->value('price_cents'));
        $this->assertNull(CatalogPresenter::lotInfo($session));
    }

    public function test_organizer_manages_lots_in_the_panel(): void
    {
        $session = $this->makeSession(4, 4500);
        $organizer = PanelUser::factory()->create();

        $this->actingAs($organizer, 'painel')->get(route('admin.lots', $session))->assertOk();

        $this->actingAs($organizer, 'painel')
            ->post(route('admin.lots.store', $session), ['name' => '1º lote', 'price' => '45', 'quantity' => 2])
            ->assertSessionHasNoErrors();
        $this->actingAs($organizer, 'painel')
            ->post(route('admin.lots.store', $session), ['name' => '2º lote', 'price' => '60.50', 'quantity' => ''])
            ->assertSessionHasNoErrors();

        $lots = TicketLot::where('session_id', $session->id)->orderBy('position')->get();
        $this->assertSame([0, 1], $lots->pluck('position')->all());
        $this->assertSame(6050, $lots[1]->price_cents);
        $this->assertNull($lots[1]->quantity);

        $this->hold($session, 1);

        // Lote com venda não pode encolher abaixo do vendido nem ser removido.
        $this->actingAs($organizer, 'painel')
            ->put(route('admin.lots.update', $lots[0]), ['name' => '1º lote', 'price' => '45', 'quantity' => 0])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($organizer, 'painel')->delete(route('admin.lots.destroy', $lots[0]));
        $this->assertDatabaseHas('ticket_lots', ['id' => $lots[0]->id]);

        $this->actingAs($organizer, 'painel')->delete(route('admin.lots.destroy', $lots[1]));
        $this->assertDatabaseMissing('ticket_lots', ['id' => $lots[1]->id]);
    }

    public function test_staff_cannot_manage_lots(): void
    {
        $session = $this->makeSession(1);
        $staff = PanelUser::factory()->create(['role' => PanelUser::ROLE_STAFF]);

        $this->actingAs($staff, 'painel')->get(route('admin.lots', $session))->assertForbidden();
    }
}
