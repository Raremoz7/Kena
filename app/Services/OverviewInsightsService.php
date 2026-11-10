<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Ticket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Números extras da visão geral do painel: cupons, cortesias e picos de
 * compra / escolha de lugar. Só leitura — nada aqui grava no banco.
 */
class OverviewInsightsService
{
    /** Quantos dias o gráfico por data mostra (os mais recentes com movimento). */
    private const DAYS = 14;

    /** @return array<string, mixed> */
    public function build(): array
    {
        return [
            'coupons' => $this->coupons(),
            'courtesies' => $this->courtesies(),
            'activity' => $this->activity(),
        ];
    }

    /**
     * Compras pagas que usaram cupom, agrupadas pelo tipo do cupom.
     *
     * @return array{ordersWithCoupon: int, paidOrders: int, ticketsWithCoupon: int, byKind: array<int, array{label: string, orders: int, tickets: int}>}
     */
    private function coupons(): array
    {
        $paidOrders = Order::where('status', Order::STATUS_PAID)->count();

        $orders = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereNotNull('coupon_id')
            ->with('coupon:id,type,value')
            ->get(['id', 'coupon_id']);

        $tickets = Ticket::query()
            ->whereIn('order_id', $orders->pluck('id'))
            ->selectRaw('order_id, count(*) as total')
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        $byKind = $orders
            ->groupBy(fn (Order $o): string => $this->kindLabel($o->coupon))
            ->map(fn (Collection $group, string $label): array => [
                'label' => $label,
                'orders' => $group->count(),
                'tickets' => (int) $group->sum(fn (Order $o): int => (int) ($tickets[$o->id] ?? 0)),
            ])
            ->sortByDesc('orders')
            ->values()
            ->all();

        return [
            'ordersWithCoupon' => $orders->count(),
            'paidOrders' => $paidOrders,
            'ticketsWithCoupon' => (int) $tickets->sum(),
            'byKind' => $byKind,
        ];
    }

    private function kindLabel(?Coupon $coupon): string
    {
        if ($coupon === null) {
            return 'Cupom removido';
        }

        return match ($coupon->type) {
            Coupon::TYPE_TICKETS => $coupon->value === 1
                ? 'Cortesia · 1 ingresso'
                : "Cortesia · {$coupon->value} ingressos",
            Coupon::TYPE_PERCENT => "{$coupon->value}% de desconto",
            Coupon::TYPE_FIXED => 'R$ '.number_format($coupon->value / 100, 2, ',', '.').' de desconto',
            default => 'Outro',
        };
    }

    /**
     * Cortesias (cupom do tipo "ingressos"): mesma regra da tela de cortesias —
     * só pedido pago conta como resgate.
     *
     * @return array{total: int, redeemed: int, available: int, expired: int, ticketsRedeemed: int, ticketsAvailable: int}
     */
    private function courtesies(): array
    {
        $coupons = Coupon::where('type', Coupon::TYPE_TICKETS)->get(['id', 'value', 'active', 'expires_at']);

        $redeemedIds = CouponRedemption::query()
            ->whereIn('coupon_id', $coupons->pluck('id'))
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->pluck('coupon_id')
            ->flip();

        $redeemed = $coupons->filter(fn (Coupon $c): bool => $redeemedIds->has($c->id));
        $open = $coupons->reject(fn (Coupon $c): bool => $redeemedIds->has($c->id));
        $expired = $open->filter(fn (Coupon $c): bool => ! $c->active || ($c->expires_at !== null && $c->expires_at->isPast()));
        $available = $open->diffKeys($expired);

        return [
            'total' => $coupons->count(),
            'redeemed' => $redeemed->count(),
            'available' => $available->count(),
            'expired' => $expired->count(),
            'ticketsRedeemed' => $redeemed->sum(fn (Coupon $c): int => $c->value),
            'ticketsAvailable' => $available->sum(fn (Coupon $c): int => $c->value),
        ];
    }

    /**
     * Compras pagas (pelo horário do pagamento) e escolhas de lugar (cada
     * reserva criada = alguém escolheu assentos) por hora do dia e por data.
     *
     * @return array{byHour: array<int, array{hour: int, purchases: int, selections: int}>, byDay: array<int, array{date: string, purchases: int, selections: int}>}
     */
    private function activity(): array
    {
        $purchases = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->get(['paid_at', 'created_at'])
            ->map(fn (Order $o): ?CarbonInterface => $o->paid_at ?? $o->created_at)
            ->filter();

        $selections = Reservation::query()
            ->get(['created_at'])
            ->map(fn (Reservation $r): ?CarbonInterface => $r->created_at)
            ->filter();

        $tz = (string) config('app.timezone');
        $local = fn (Collection $dates): Collection => $dates->map(fn (CarbonInterface $d): Carbon => Carbon::instance($d)->setTimezone($tz));
        $purchases = $local($purchases);
        $selections = $local($selections);

        $countBy = fn (Collection $dates, callable $key): Collection => $dates->countBy($key);
        $pHour = $countBy($purchases, fn (Carbon $d): int => $d->hour);
        $sHour = $countBy($selections, fn (Carbon $d): int => $d->hour);
        $pDay = $countBy($purchases, fn (Carbon $d): string => $d->toDateString());
        $sDay = $countBy($selections, fn (Carbon $d): string => $d->toDateString());

        $byHour = collect(range(0, 23))->map(fn (int $h): array => [
            'hour' => $h,
            'purchases' => (int) ($pHour[$h] ?? 0),
            'selections' => (int) ($sHour[$h] ?? 0),
        ])->all();

        $byDay = $pDay->keys()->merge($sDay->keys())->unique()->sort()->values()
            ->take(-self::DAYS)
            ->map(fn (string $date): array => [
                'date' => $date,
                'purchases' => (int) ($pDay[$date] ?? 0),
                'selections' => (int) ($sDay[$date] ?? 0),
            ])
            ->values()
            ->all();

        return ['byHour' => $byHour, 'byDay' => $byDay];
    }
}
