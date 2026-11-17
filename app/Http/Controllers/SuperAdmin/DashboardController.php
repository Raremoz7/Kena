<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Refund;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Visão financeira da plataforma: vendas e taxas de todos os organizadores
 * no período, por organização e mês a mês. Valores de pedidos pagos (antes de
 * estornos); os estornos do período aparecem à parte.
 */
class DashboardController extends Controller
{
    /** @var array<string, string> */
    public const PERIODS = ['7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias', '12m' => 'Últimos 12 meses', 'all' => 'Tudo'];

    public function __invoke(Request $request): Response
    {
        $period = (string) $request->query('periodo', '30d');
        if (! array_key_exists($period, self::PERIODS)) {
            $period = '30d';
        }
        $since = self::since($period);

        $paid = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->when($since !== null, fn ($q) => $q->where('paid_at', '>=', $since));

        $totals = (clone $paid)->toBase()->selectRaw('count(*) as orders, coalesce(sum(total_cents), 0) as gross, coalesce(sum(fee_cents), 0) as fees')->first();
        $refunded = (int) Refund::query()
            ->where('status', Refund::STATUS_DONE)
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->sum('amount_cents');

        $names = Organization::query()->pluck('name', 'id');
        $byOrganization = (clone $paid)
            ->toBase()
            ->selectRaw('organization_id, count(*) as orders, sum(total_cents) as gross, sum(fee_cents) as fees')
            ->groupBy('organization_id')
            ->orderByDesc('gross')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->organization_id,
                'name' => (string) ($names[$row->organization_id] ?? '—'),
                'orders' => (int) $row->orders,
                'gross' => Money::toReais((int) $row->gross),
                'fees' => Money::toReais((int) $row->fees),
            ])
            ->values();

        return Inertia::render('super/dashboard', [
            'period' => $period,
            'periods' => collect(self::PERIODS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'kpis' => [
                'orders' => (int) ($totals->orders ?? 0),
                'gross' => Money::toReais((int) ($totals->gross ?? 0)),
                'fees' => Money::toReais((int) ($totals->fees ?? 0)),
                'refunded' => Money::toReais($refunded),
                'organizations' => $names->count(),
                'activeOrganizations' => Organization::query()->where('status', Organization::STATUS_ACTIVE)->count(),
            ],
            'byOrganization' => $byOrganization,
            'months' => $this->months(),
        ]);
    }

    private static function since(string $period): ?CarbonInterface
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '12m' => now()->subMonths(12),
            'all' => null,
            default => now()->subDays(30),
        };
    }

    /**
     * Últimos 12 meses (vendido e taxa), agrupados em PHP para funcionar igual
     * em SQLite e MySQL.
     *
     * @return list<array{month: string, label: string, gross: float, fees: float}>
     */
    private function months(): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $buckets[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'label' => $m->translatedFormat('M/y'), 'gross' => 0, 'fees' => 0];
        }

        Order::query()
            ->where('status', Order::STATUS_PAID)
            ->where('paid_at', '>=', $start)
            ->select(['id', 'paid_at', 'total_cents', 'fee_cents'])
            ->lazyById(1000)
            ->each(function (Order $order) use (&$buckets): void {
                $key = $order->paid_at?->format('Y-m');
                if ($key !== null && isset($buckets[$key])) {
                    $buckets[$key]['gross'] += $order->total_cents;
                    $buckets[$key]['fees'] += $order->fee_cents;
                }
            });

        return array_values(array_map(fn (array $b): array => [
            'month' => $b['month'],
            'label' => $b['label'],
            'gross' => Money::toReais((int) $b['gross']),
            'fees' => Money::toReais((int) $b['fees']),
        ], $buckets));
    }
}
