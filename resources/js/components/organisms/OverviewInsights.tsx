import type { ReactNode } from 'react';

export interface OverviewInsightsData {
    coupons: {
        ordersWithCoupon: number;
        paidOrders: number;
        ticketsWithCoupon: number;
        byKind: { label: string; orders: number; tickets: number }[];
    };
    courtesies: {
        total: number;
        redeemed: number;
        available: number;
        expired: number;
        ticketsRedeemed: number;
        ticketsAvailable: number;
    };
    activity: {
        byHour: { hour: number; purchases: number; selections: number }[];
        byDay: { date: string; purchases: number; selections: number }[];
    };
}

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-card border border-border bg-surface p-4 sm:p-5">
            <h2 className="kicker text-faint">{title}</h2>
            {description && (
                <p className="mt-1 font-body text-xs text-muted-foreground">
                    {description}
                </p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="py-6 text-center font-body text-sm text-muted-foreground">
            {children}
        </p>
    );
}

const SERIES = {
    purchases: { label: 'Compras pagas', bar: 'bg-accent' },
    selections: { label: 'Escolhas de lugar', bar: 'bg-info' },
} as const;

function Legend() {
    return (
        <div className="mb-3 flex flex-wrap gap-4 font-body text-xs text-muted-foreground">
            {Object.values(SERIES).map((s) => (
                <span
                    key={s.label}
                    className="inline-flex items-center gap-1.5"
                >
                    <span className={`size-2.5 rounded-sm ${s.bar}`} />
                    {s.label}
                </span>
            ))}
        </div>
    );
}

/** Colunas agrupadas (compras x escolhas de lugar) — sem biblioteca de gráfico. */
function PairedColumns({
    rows,
}: {
    rows: {
        key: string;
        label: string;
        purchases: number;
        selections: number;
    }[];
}) {
    const max = Math.max(
        1,
        ...rows.flatMap((r) => [r.purchases, r.selections]),
    );
    const pct = (v: number) => `${(v / max) * 100}%`;

    return (
        <>
            <Legend />
            <div className="overflow-x-auto">
                <div
                    className="flex h-40 min-w-[560px] items-end gap-1"
                    role="img"
                    aria-label="Gráfico de colunas de compras pagas e escolhas de lugar"
                >
                    {rows.map((r) => (
                        <div
                            key={r.key}
                            className="group relative flex h-full flex-1 flex-col justify-end"
                        >
                            <div className="flex h-full items-end justify-center gap-px">
                                <div
                                    className={`w-1/2 max-w-3 rounded-t-sm ${SERIES.purchases.bar}`}
                                    style={{ height: pct(r.purchases) }}
                                />
                                <div
                                    className={`w-1/2 max-w-3 rounded-t-sm ${SERIES.selections.bar}`}
                                    style={{ height: pct(r.selections) }}
                                />
                            </div>
                            <div className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md border border-border bg-popover px-2 py-1 font-body text-[11px] whitespace-nowrap text-popover-foreground shadow group-hover:block">
                                <strong>{r.label}</strong>
                                <br />
                                {r.purchases} compra(s) · {r.selections}{' '}
                                escolha(s)
                            </div>
                        </div>
                    ))}
                </div>
                <div className="flex min-w-[560px] gap-1 pt-1">
                    {rows.map((r) => (
                        <span
                            key={r.key}
                            className="tabular flex-1 text-center font-body text-[10px] text-faint"
                        >
                            {r.label}
                        </span>
                    ))}
                </div>
            </div>
        </>
    );
}

function CouponColumns({
    rows,
}: {
    rows: OverviewInsightsData['coupons']['byKind'];
}) {
    const max = Math.max(1, ...rows.map((r) => r.orders));

    return (
        <div
            className="flex h-44 items-end gap-3"
            role="img"
            aria-label="Compras com cupom por tipo"
        >
            {rows.map((r) => (
                <div
                    key={r.label}
                    className="flex h-full min-w-0 flex-1 flex-col items-center justify-end"
                >
                    <span className="tabular font-body text-xs font-semibold text-foreground">
                        {r.orders}
                    </span>
                    <div
                        className="mt-1 w-full max-w-16 rounded-t-md bg-accent"
                        style={{ height: `${(r.orders / max) * 100}%` }}
                        title={`${r.orders} compra(s) · ${r.tickets} ingresso(s)`}
                    />
                    <span className="mt-2 line-clamp-2 text-center font-body text-[11px] leading-tight text-muted-foreground">
                        {r.label}
                    </span>
                    <span className="tabular font-body text-[10px] text-faint">
                        {r.tickets} ingr.
                    </span>
                </div>
            ))}
        </div>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: ReactNode;
    tone?: string;
}) {
    return (
        <div>
            <p className="tabular font-display text-display-sm text-foreground">
                <span className={tone}>{value}</span>
            </p>
            <p className="font-body text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

const formatDay = (iso: string) => {
    const [, m, d] = iso.split('-');

    return `${d}/${m}`;
};

export function OverviewInsights({ data }: { data: OverviewInsightsData }) {
    const { coupons, courtesies, activity } = data;
    const redeemedPct =
        courtesies.total > 0
            ? Math.round((courtesies.redeemed / courtesies.total) * 100)
            : 0;
    const hasActivity = activity.byHour.some(
        (h) => h.purchases + h.selections > 0,
    );
    const peak = activity.byHour.reduce(
        (best, h) => (h.purchases > best.purchases ? h : best),
        activity.byHour[0] ?? { hour: 0, purchases: 0, selections: 0 },
    );

    return (
        <div className="mt-6 grid gap-3 lg:grid-cols-2">
            <Card
                title="Compras com cupom"
                description={`${coupons.ordersWithCoupon} de ${coupons.paidOrders} compras pagas usaram cupom · ${coupons.ticketsWithCoupon} ingressos nessas compras`}
            >
                {coupons.byKind.length > 0 ? (
                    <CouponColumns rows={coupons.byKind} />
                ) : (
                    <Empty>Nenhuma compra paga com cupom ainda.</Empty>
                )}
            </Card>

            <Card
                title="Cortesias"
                description="Só conta como resgatada a cortesia de um pedido pago."
            >
                {courtesies.total > 0 ? (
                    <>
                        <div className="grid grid-cols-3 gap-3">
                            <Stat
                                label="Resgatadas"
                                value={courtesies.redeemed}
                                tone="text-accent-text"
                            />
                            <Stat
                                label="Disponíveis"
                                value={courtesies.available}
                            />
                            <Stat
                                label="Expiradas / inativas"
                                value={courtesies.expired}
                            />
                        </div>
                        <div
                            className="mt-4 h-2 overflow-hidden rounded-full bg-surface-2"
                            role="meter"
                            aria-label="Cortesias resgatadas"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={redeemedPct}
                        >
                            <div
                                className="h-full rounded-full bg-accent"
                                style={{ width: `${redeemedPct}%` }}
                            />
                        </div>
                        <p className="mt-2 font-body text-xs text-muted-foreground">
                            {redeemedPct}% de {courtesies.total} cortesia(s) ·{' '}
                            {courtesies.ticketsRedeemed} ingresso(s) grátis já
                            emitidos · {courtesies.ticketsAvailable} ainda a
                            resgatar
                        </p>
                    </>
                ) : (
                    <Empty>Nenhuma cortesia criada.</Empty>
                )}
            </Card>

            <div className="lg:col-span-2">
                <Card
                    title="Horários de pico"
                    description={
                        hasActivity && peak.purchases > 0
                            ? `Pico de compras às ${String(peak.hour).padStart(2, '0')}h (${peak.purchases} compra(s)). Soma de todos os dias.`
                            : 'Por hora do dia, somando todos os dias.'
                    }
                >
                    {hasActivity ? (
                        <PairedColumns
                            rows={activity.byHour.map((h) => ({
                                key: String(h.hour),
                                label: `${String(h.hour).padStart(2, '0')}h`,
                                purchases: h.purchases,
                                selections: h.selections,
                            }))}
                        />
                    ) : (
                        <Empty>Sem movimento ainda.</Empty>
                    )}
                </Card>
            </div>

            <div className="lg:col-span-2">
                <Card
                    title="Movimento por data"
                    description="Últimos dias com movimento."
                >
                    {activity.byDay.length > 0 ? (
                        <PairedColumns
                            rows={activity.byDay.map((d) => ({
                                key: d.date,
                                label: formatDay(d.date),
                                purchases: d.purchases,
                                selections: d.selections,
                            }))}
                        />
                    ) : (
                        <Empty>Sem movimento ainda.</Empty>
                    )}
                </Card>
            </div>
        </div>
    );
}
