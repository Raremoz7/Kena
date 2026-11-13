<?php

namespace Tests\Feature\Kena;

use App\Mail\RefundConfirmedMail;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\PaymentService;
use App\Services\Payments\PaymentGateway;
use App\Services\SeatReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\Feature\Kena\Support\FakeGateway;
use Tests\TestCase;

/**
 * Reembolso por ingresso no painel: devolve só o assento escolhido, com a
 * fatia proporcional do que foi pago, e fecha o pedido quando não sobra nada.
 */
class PartialRefundTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
        Mail::fake();
    }

    /** Pedido pago no cartão com $seats assentos de R$ 45 (taxa de serviço incluída no total). */
    private function paidOrder(int $seats = 2, ?string $couponCode = null): Order
    {
        $session = $this->makeSession($seats, 4500);
        $user = User::factory()->create();
        $reservation = app(SeatReservationService::class)
            ->hold($session, $user, $this->availableSeatIds($session, $seats));

        $order = app(PaymentService::class)->pay($reservation, array_filter([
            'method' => 'card', 'token' => 'tok', 'coupon_code' => $couponCode,
        ]));

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);

        return $order;
    }

    /** @return array<int, Ticket> ingressos do pedido em ordem de assento */
    private function tickets(Order $order): array
    {
        return Ticket::where('order_id', $order->id)->orderBy('seat_code')->get()->all();
    }

    /** @param  array<int, int>  $ticketIds */
    private function refundTickets(Order $order, array $ticketIds): TestResponse
    {
        return $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->from(route('admin.orders'))
            ->post(route('admin.orders.refund-tickets', $order), ['ticket_ids' => $ticketIds]);
    }

    public function test_refunding_one_ticket_keeps_the_rest_of_the_order_valid(): void
    {
        $order = $this->paidOrder(2);
        [$first, $second] = $this->tickets($order);

        $this->refundTickets($order, [$first->id])
            ->assertRedirect(route('admin.orders'))
            ->assertSessionHasNoErrors();

        $this->assertSame(Ticket::STATUS_REFUNDED, $first->refresh()->status);
        $this->assertSame(Ticket::STATUS_VALID, $second->refresh()->status, 'o outro ingresso continua valendo');

        $this->assertSame(SessionSeat::STATUS_AVAILABLE, SessionSeat::find($first->session_seat_id)->status, 'assento devolvido volta à venda');
        $this->assertSame(SessionSeat::STATUS_SOLD, SessionSeat::find($second->session_seat_id)->status);

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status, 'pedido com ingresso vivo continua pago');
        $this->assertSame(Payment::STATUS_APPROVED, $order->payment->status);

        // Fatia proporcional ao total pago (taxa rateada), arredondada para baixo.
        $expected = intdiv(4500 * $order->total_cents, $order->subtotal_cents);
        $refund = Refund::sole();
        $this->assertSame($first->id, $refund->ticket_id);
        $this->assertSame($expected, $refund->amount_cents);
        $this->assertSame(Refund::STATUS_DONE, $refund->status);
        $this->assertSame([$expected], $this->gateway->refundAmounts, 'gateway recebe estorno parcial do valor do assento');
        $this->assertStringContainsString('-tickets-'.$first->id, (string) $this->gateway->refundKeys[0]);

        Mail::assertQueued(RefundConfirmedMail::class, fn (RefundConfirmedMail $mail): bool => $mail->partial
            && $mail->amountCents === $expected
            && $mail->seatCodes === [$first->seat_code]);
    }

    public function test_refunding_the_last_ticket_closes_the_order_and_returns_every_cent(): void
    {
        $order = $this->paidOrder(2);
        [$first, $second] = $this->tickets($order);

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();
        $this->refundTickets($order, [$second->id])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_REFUNDED, $order->status);
        $this->assertSame(Payment::STATUS_REFUNDED, $order->payment->status);
        $this->assertSame(Ticket::STATUS_REFUNDED, $second->refresh()->status);

        // Dois estornos cujos valores somam exatamente o que entrou — nem um centavo a mais ou a menos.
        $this->assertCount(2, $this->gateway->refundAmounts);
        $this->assertSame($order->total_cents, (int) Refund::where('status', Refund::STATUS_DONE)->sum('amount_cents'));
        $this->assertSame($order->total_cents, (int) array_sum($this->gateway->refundAmounts));
        $this->assertNotSame($this->gateway->refundKeys[0], $this->gateway->refundKeys[1], 'ingressos de mesmo preço não podem compartilhar chave de idempotência');

        Mail::assertQueued(RefundConfirmedMail::class, fn (RefundConfirmedMail $mail): bool => ! $mail->partial);
    }

    public function test_selecting_every_ticket_at_once_refunds_the_whole_order_in_one_call(): void
    {
        $order = $this->paidOrder(2);
        $ids = array_map(fn (Ticket $t): int => $t->id, $this->tickets($order));

        $this->refundTickets($order, $ids)->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        // Devolvendo tudo o que entrou, o gateway recebe um estorno total (sem valor).
        $this->assertSame([null], $this->gateway->refundAmounts);
        $this->assertSame($order->total_cents, (int) Refund::sum('amount_cents'));
        $this->assertSame(2, Refund::whereNotNull('ticket_id')->count());
    }

    public function test_coupon_is_rated_into_the_share_and_only_released_when_the_order_closes(): void
    {
        $coupon = Coupon::create([
            'code' => 'DEZ', 'type' => Coupon::TYPE_PERCENT, 'value' => 10,
            'max_uses' => 1, 'used' => 0, 'active' => true,
        ]);
        $order = $this->paidOrder(2, 'DEZ');
        [$first, $second] = $this->tickets($order);

        $this->assertGreaterThan(0, $order->discount_cents, 'cupom aplicado no pedido');
        $this->assertSame(1, $coupon->refresh()->used);

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();

        $expected = intdiv(4500 * $order->total_cents, $order->subtotal_cents);
        $this->assertLessThan(4500, $expected, 'desconto rateado: devolve menos que o preço de tabela');
        $this->assertSame($expected, Refund::sole()->amount_cents);
        $this->assertSame(1, $coupon->refresh()->used, 'cupom segue usado enquanto houver ingresso vivo');
        $this->assertSame(1, CouponRedemption::count());

        $this->refundTickets($order, [$second->id])->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_REFUNDED, $order->refresh()->status);
        $this->assertSame(0, $coupon->refresh()->used, 'pedido fechado devolve o uso do cupom');
        $this->assertSame(0, CouponRedemption::count());
    }

    public function test_used_ticket_cannot_be_refunded(): void
    {
        $order = $this->paidOrder(2);
        [$first, $second] = $this->tickets($order);
        app(CheckInService::class)->admit($first, $order->session, null);

        $this->refundTickets($order, [$first->id, $second->id])
            ->assertSessionHasErrors('order');

        $this->assertSame(Ticket::STATUS_USED, $first->refresh()->status);
        $this->assertSame(Ticket::STATUS_VALID, $second->refresh()->status, 'lote inválido não mexe em nenhum ingresso');
        $this->assertSame(0, Refund::count());
        $this->assertSame([], $this->gateway->refunds);
    }

    public function test_ticket_from_another_order_is_rejected(): void
    {
        $order = $this->paidOrder(1);
        $other = $this->paidOrder(1);
        [$foreign] = $this->tickets($other);

        $this->refundTickets($order, [$foreign->id])->assertSessionHasErrors('order');

        $this->assertSame(Ticket::STATUS_VALID, $foreign->refresh()->status);
        $this->assertSame(0, Refund::count());
    }

    public function test_gateway_refusal_restores_the_ticket_and_reports_the_failure(): void
    {
        $order = $this->paidOrder(2);
        [$first] = $this->tickets($order);
        $this->gateway->refundOk = false;

        $this->refundTickets($order, [$first->id])->assertSessionHasErrors('order');

        $this->assertSame(Ticket::STATUS_VALID, $first->refresh()->status, 'estorno recusado devolve o ingresso');
        $this->assertSame(SessionSeat::STATUS_SOLD, SessionSeat::find($first->session_seat_id)->status);
        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);

        $refund = Refund::sole();
        $this->assertSame(Refund::STATUS_FAILED, $refund->status);
        $this->assertSame($first->id, $refund->ticket_id);
        Mail::assertNotQueued(RefundConfirmedMail::class);

        // Painel sinaliza a falha para o organizador reprocessar.
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p->where('orders.data.0.refundFailed', true));
    }

    public function test_full_refund_after_a_partial_one_returns_only_the_remainder(): void
    {
        $order = $this->paidOrder(2);
        [$first, $second] = $this->tickets($order);

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post(route('admin.orders.refund', $order))
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(Order::STATUS_REFUNDED, $order->status);
        $this->assertSame(Ticket::STATUS_REFUNDED, $second->refresh()->status);
        $this->assertSame($order->total_cents, (int) array_sum($this->gateway->refundAmounts), 'total devolvido = total pago, sem estornar o primeiro assento duas vezes');
        $this->assertSame($order->total_cents, (int) Refund::where('status', Refund::STATUS_DONE)->sum('amount_cents'));
    }

    public function test_full_refund_does_not_free_a_seat_already_resold_to_someone_else(): void
    {
        $order = $this->paidOrder(2);
        [$first] = $this->tickets($order);

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();

        // Outra pessoa compra o assento devolvido.
        $session = $order->session;
        $buyer = User::factory()->create();
        $reservation = app(SeatReservationService::class)->hold($session, $buyer, [$first->session_seat_id]);
        $resale = app(PaymentService::class)->pay($reservation, ['method' => 'card', 'token' => 'tok']);
        $this->assertSame(Order::STATUS_PAID, $resale->refresh()->status);

        // Reembolso total do pedido original não pode derrubar o assento do novo comprador.
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->post(route('admin.orders.refund', $order))
            ->assertSessionHasNoErrors();

        $seat = SessionSeat::find($first->session_seat_id);
        $this->assertSame(SessionSeat::STATUS_SOLD, $seat->status);
        $this->assertSame($resale->id, $seat->sold_by_order_id);
        $this->assertSame(Ticket::STATUS_VALID, Ticket::where('order_id', $resale->id)->sole()->status);
    }

    public function test_overview_revenue_excludes_what_went_back_to_the_buyer(): void
    {
        $order = $this->paidOrder(2);
        [$first] = $this->tickets($order);

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();

        $refunded = Refund::sole()->amount_cents;
        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('painel'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kpis.revenue', ($order->total_cents - $refunded) / 100));
    }

    public function test_orders_page_lists_tickets_with_their_refundable_share(): void
    {
        $order = $this->paidOrder(2);
        [$first] = $this->tickets($order);
        $expected = intdiv(4500 * $order->total_cents, $order->subtotal_cents) / 100;

        $this->refundTickets($order, [$first->id])->assertSessionHasNoErrors();

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('admin.orders'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('orders.data.0.status', Order::STATUS_PAID)
                ->where('orders.data.0.partiallyRefunded', true)
                ->where('orders.data.0.remaining', ($order->total_cents - Refund::sole()->amount_cents) / 100)
                ->has('orders.data.0.tickets', 2)
                ->where('orders.data.0.tickets.0.status', Ticket::STATUS_REFUNDED)
                ->where('orders.data.0.tickets.1.status', Ticket::STATUS_VALID)
                ->where('orders.data.0.tickets.1.amount', $expected));
    }
}
