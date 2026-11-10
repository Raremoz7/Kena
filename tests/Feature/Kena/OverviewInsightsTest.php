<?php

namespace Tests\Feature\Kena;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\PanelUser;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Visão geral: cupons, cortesias e horários de pico (só leitura). */
class OverviewInsightsTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_overview_reports_coupons_courtesies_and_peaks(): void
    {
        $session = $this->makeSession(4, 4500);
        $user = User::factory()->create();

        $courtesyA = Coupon::create(['code' => 'CORT-A', 'type' => Coupon::TYPE_TICKETS, 'value' => 2, 'max_uses' => 1, 'used' => 1]);
        $courtesyB = Coupon::create(['code' => 'CORT-B', 'type' => Coupon::TYPE_TICKETS, 'value' => 2, 'max_uses' => 1]);
        Coupon::create(['code' => 'CORT-C', 'type' => Coupon::TYPE_TICKETS, 'value' => 1, 'max_uses' => 1, 'active' => false]);
        $percent = Coupon::create(['code' => 'NOITE10', 'type' => Coupon::TYPE_PERCENT, 'value' => 10]);

        $order = fn (string $ref, string $status, ?Coupon $c, string $paidAt): Order => Order::forceCreate([
            'user_id' => $user->id, 'session_id' => $session->id, 'reference' => $ref,
            'coupon_id' => $c?->id, 'subtotal_cents' => 9000, 'discount_cents' => 0,
            'total_cents' => 9000, 'status' => $status,
            'paid_at' => $status === Order::STATUS_PAID ? $paidAt : null,
        ]);

        $paidCourtesy = $order('KNA-1', Order::STATUS_PAID, $courtesyA, '2026-11-09 10:15:00');
        $order('KNA-2', Order::STATUS_PAID, $percent, '2026-11-09 10:40:00');
        $order('KNA-3', Order::STATUS_PAID, null, '2026-11-09 21:05:00');
        // Pedido falho com cortesia: não conta como resgate nem como compra.
        $failed = $order('KNA-4', Order::STATUS_FAILED, $courtesyB, '2026-11-09 11:00:00');

        CouponRedemption::create(['coupon_id' => $courtesyA->id, 'order_id' => $paidCourtesy->id, 'user_id' => $user->id, 'discount_cents' => 9000]);
        CouponRedemption::create(['coupon_id' => $courtesyB->id, 'order_id' => $failed->id, 'user_id' => $user->id, 'discount_cents' => 9000]);

        // Pagamentos: KNA-1 cortesia total, KNA-2 cartão, KNA-3 Pix depois de um Pix vencido; KNA-4 cartão recusado.
        $pay = fn (Order $o, string $method, string $status, int $cents) => Payment::create([
            'order_id' => $o->id, 'method' => $method, 'status' => $status, 'amount_cents' => $cents,
        ]);
        $pay($paidCourtesy, Payment::METHOD_FREE, Payment::STATUS_APPROVED, 0);
        $pay(Order::where('reference', 'KNA-2')->firstOrFail(), Payment::METHOD_CARD, Payment::STATUS_APPROVED, 9000);
        $kna3 = Order::where('reference', 'KNA-3')->firstOrFail();
        $pay($kna3, Payment::METHOD_PIX, Payment::STATUS_CANCELLED, 9000);
        $pay($kna3, Payment::METHOD_PIX, Payment::STATUS_APPROVED, 9000);
        $pay($failed, Payment::METHOD_CARD, Payment::STATUS_REJECTED, 9000);

        foreach (['2026-11-09 10:05:00', '2026-11-09 10:30:00', '2026-11-08 18:00:00'] as $at) {
            Reservation::forceCreate([
                'session_id' => $session->id, 'user_id' => $user->id,
                'status' => Reservation::STATUS_CONVERTED, 'expires_at' => $at,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('painel'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('insights.coupons.paidOrders', 3)
                ->where('insights.coupons.ordersWithCoupon', 2)
                ->where('insights.coupons.byKind', fn ($rows) => collect($rows)->pluck('orders', 'label')->all() === [
                    'Cortesia · 2 ingressos' => 1,
                    '10% de desconto' => 1,
                ])
                ->where('insights.courtesies.total', 3)
                ->where('insights.courtesies.redeemed', 1)
                ->where('insights.courtesies.available', 1)
                ->where('insights.courtesies.expired', 1)
                ->where('insights.courtesies.ticketsRedeemed', 2)
                ->where('insights.courtesies.ticketsAvailable', 2)
                ->where('insights.activity.byHour.10.purchases', 2)
                ->where('insights.activity.byHour.10.selections', 2)
                ->where('insights.activity.byHour.21.purchases', 1)
                ->where('insights.activity.byHour.18.selections', 1)
                ->where('insights.payments', fn ($rows) => collect($rows)->pluck('orders', 'method')->sortKeys()->all() === [
                    'card' => 1, 'free' => 1, 'pix' => 1,
                ])
                ->where('insights.funnel', ['selected' => 1, 'ordered' => 1, 'paid' => 1])
                ->where('insights.failures.cardRejected', 1)
                ->where('insights.failures.pixExpired', 1)
                ->where('insights.failures.people', 1)
                ->where('insights.failures.recovered', 1)
                ->where('insights.averages.ticket', 90)
                ->where('insights.revenueByDay', [
                    ['date' => '2026-11-09', 'amount' => 270, 'cumulative' => 270],
                ])
                ->where('insights.signups.0.count', fn ($c) => $c >= 1)
                ->where('insights.checkIn.total', 0)
                ->where('insights.activity.byDay', [
                    ['date' => '2026-11-08', 'purchases' => 0, 'selections' => 1],
                    ['date' => '2026-11-09', 'purchases' => 3, 'selections' => 2],
                ]));
    }
}
