<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Ticket;
use App\Models\User;
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
            'payments' => $this->payments(),
            'funnel' => $this->funnel(),
            'failures' => $this->failures(),
            'averages' => $this->averages(),
            'revenueByDay' => $this->revenueByDay(),
            'checkIn' => $this->checkIn(),
            'signups' => $this->signups(),
        ];
    }

    /**
     * Forma de pagamento das compras pagas (pagamento aprovado de cada pedido).
     *
     * @return array<int, array{method: string, label: string, orders: int, amount: float}>
     */
    private function payments(): array
    {
        $labels = [
            Payment::METHOD_PIX => 'Pix',
            Payment::METHOD_CARD => 'Cartão',
            Payment::METHOD_FREE => 'Cortesia (R$ 0)',
        ];

        return Payment::query()
            ->where('status', Payment::STATUS_APPROVED)
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID))
            ->selectRaw('method, count(distinct order_id) as orders, sum(amount_cents) as cents')
            ->groupBy('method')
            ->get()
            ->map(fn (Payment $p): array => [
                'method' => (string) $p->method,
                'label' => $labels[$p->method] ?? ucfirst((string) $p->method),
                'orders' => (int) $p->getAttribute('orders'),
                'amount' => (int) $p->getAttribute('cents') / 100,
            ])
            ->sortByDesc('orders')
            ->values()
            ->all();
    }

    /**
     * Pessoas em cada etapa: escolheram lugar → criaram pedido → pagaram.
     *
     * @return array{selected: int, ordered: int, paid: int}
     */
    private function funnel(): array
    {
        return [
            'selected' => Reservation::query()->distinct()->count('user_id'),
            'ordered' => Order::query()->distinct()->count('user_id'),
            'paid' => Order::where('status', Order::STATUS_PAID)->distinct()->count('user_id'),
        ];
    }

    /**
     * Cartões recusados e Pix que venceram sem pagamento, e quantas dessas
     * pessoas acabaram comprando mesmo assim.
     *
     * @return array{cardRejected: int, cardRejectedAmount: float, pixExpired: int, pixExpiredAmount: float, people: int, recovered: int}
     */
    private function failures(): array
    {
        $failed = Payment::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('method', Payment::METHOD_CARD)->where('status', Payment::STATUS_REJECTED))
                ->orWhere(fn ($q) => $q->where('method', Payment::METHOD_PIX)->where('status', Payment::STATUS_CANCELLED)))
            ->with('order:id,user_id')
            ->get(['id', 'order_id', 'method', 'amount_cents']);

        $card = $failed->where('method', Payment::METHOD_CARD);
        $pix = $failed->where('method', Payment::METHOD_PIX);
        $people = $failed->map(fn (Payment $p): int => $p->order->user_id)->unique();
        $recovered = $people->isEmpty() ? 0 : Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereIn('user_id', $people)
            ->distinct()
            ->count('user_id');

        return [
            'cardRejected' => $card->count(),
            'cardRejectedAmount' => $card->sum(fn (Payment $p): int => $p->amount_cents) / 100,
            'pixExpired' => $pix->count(),
            'pixExpiredAmount' => $pix->sum(fn (Payment $p): int => $p->amount_cents) / 100,
            'people' => $people->count(),
            'recovered' => $recovered,
        ];
    }

    /**
     * Ticket médio (só compras com valor) e ingressos por compra paga.
     *
     * @return array{ticket: float, ticketsPerOrder: float}
     */
    private function averages(): array
    {
        // Só ingressos: pedido de fotos não tem ingresso e puxaria a média para baixo.
        $paid = Order::ticketOrders()->where('status', Order::STATUS_PAID);
        $paidCount = (clone $paid)->count();
        $withValue = (clone $paid)->where('total_cents', '>', 0);
        $valueCount = (clone $withValue)->count();
        $tickets = Ticket::whereIn('order_id', (clone $paid)->select('id'))->count();

        return [
            'ticket' => $valueCount > 0 ? round((int) $withValue->sum('total_cents') / $valueCount / 100, 2) : 0.0,
            'ticketsPerOrder' => $paidCount > 0 ? round($tickets / $paidCount, 1) : 0.0,
        ];
    }

    /**
     * Receita paga por dia (data do pagamento) e acumulada.
     *
     * @return array<int, array{date: string, amount: float, cumulative: float}>
     */
    private function revenueByDay(): array
    {
        $byDay = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->get(['paid_at', 'created_at', 'total_cents'])
            ->groupBy(fn (Order $o): string => ($o->paid_at ?? $o->created_at ?? now())->toDateString())
            ->map(fn (Collection $g): int => (int) $g->sum('total_cents'))
            ->sortKeys();

        $running = 0;
        $rows = [];
        foreach ($byDay as $date => $cents) {
            $running += $cents;
            $rows[] = ['date' => (string) $date, 'amount' => $cents / 100, 'cumulative' => $running / 100];
        }

        return $rows;
    }

    /**
     * Check-in da próxima sessão (ou da que está rolando hoje). `live` liga a
     * atualização automática no painel durante o dia do evento.
     *
     * @return array{session: string, live: bool, checkedIn: int, total: int}|null
     */
    private function checkIn(): ?array
    {
        $session = EventSession::query()
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')
            ->with('event:id,title')
            ->first();

        if ($session === null) {
            return null;
        }

        $tickets = Ticket::query()
            ->where('session_id', $session->id)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED]);

        return [
            'session' => $session->event->title.' · '.$session->starts_at->format('d/m H:i'),
            'live' => $session->starts_at->isToday(),
            'checkedIn' => (clone $tickets)->whereNotNull('checked_in_at')->count(),
            'total' => $tickets->count(),
        ];
    }

    /**
     * Contas criadas por dia (últimos dias com cadastro).
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function signups(): array
    {
        return User::query()
            ->whereNotNull('created_at')
            ->get(['created_at'])
            ->countBy(fn (User $u): string => (string) $u->created_at?->toDateString())
            ->sortKeys()
            ->take(-self::DAYS)
            ->map(fn (int $count, string $date): array => ['date' => $date, 'count' => $count])
            ->values()
            ->all();
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
