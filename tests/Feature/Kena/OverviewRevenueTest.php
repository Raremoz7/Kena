<?php

namespace Tests\Feature\Kena;

use App\Models\Order;
use App\Models\PanelUser;
use App\Models\SessionSeat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Kena\Concerns\MakesKenaData;
use Tests\TestCase;

/** Receita da visão geral = o que foi pago; cortesia (total zero) não conta. */
class OverviewRevenueTest extends TestCase
{
    use MakesKenaData;
    use RefreshDatabase;

    public function test_revenue_counts_only_what_was_paid(): void
    {
        $session = $this->makeSession(4, 4500);
        $user = User::factory()->create();
        $order = fn (string $ref, string $status, int $subtotal, int $discount): Order => Order::forceCreate([
            'user_id' => $user->id, 'session_id' => $session->id, 'reference' => $ref,
            'subtotal_cents' => $subtotal, 'discount_cents' => $discount,
            'total_cents' => $subtotal - $discount, 'status' => $status,
        ]);

        $paid = $order('KNA-1', Order::STATUS_PAID, 4500, 0);              // R$ 45 pagos
        $courtesy = $order('KNA-2', Order::STATUS_PAID, 9000, 9000);      // 2 cortesias: R$ 0
        $order('KNA-3', Order::STATUS_REFUNDED, 4500, 0);                 // estornado
        $order('KNA-4', Order::STATUS_FAILED, 4500, 0);                   // não pago

        // Os 3 assentos vendidos guardam o preço de tabela (era o que o painel somava).
        $seats = SessionSeat::where('session_id', $session->id)->orderBy('id')->get();
        $seats[0]->update(['status' => SessionSeat::STATUS_SOLD, 'sold_by_order_id' => $paid->id]);
        $seats[1]->update(['status' => SessionSeat::STATUS_SOLD, 'sold_by_order_id' => $courtesy->id]);
        $seats[2]->update(['status' => SessionSeat::STATUS_SOLD, 'sold_by_order_id' => $courtesy->id]);

        $this->actingAs(PanelUser::factory()->create(), 'painel')
            ->get(route('painel'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kpis.revenue', 45)
                ->where('kpis.sold', 3));
    }
}
