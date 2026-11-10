import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Price } from '@/components/atoms/Price';

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
    payments: {
        method: string;
        label: string;
        orders: number;
        amount: number;
    }[];
    funnel: { selected: number; ordered: number; paid: number };
    failures: {
        cardRejected: number;
        cardRejectedAmount: number;
        pixExpired: number;
        pixExpiredAmount: number;
        people: number;
        recovered: number;
    };
    averages: { ticket: number; ticketsPerOrder: number };
    revenueByDay: { date: string; amount: number; cumulative: number }[];
    checkIn: {
        session: string;
        live: boolean;
        checkedIn: number;
        total: number;
    } | null;
    signups: { date: string; count: number }[];
}

const STORAGE_KEY = 'kena.overview.expanded';

function readExpanded(): Record<string, boolean> {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}') as Record<
            string,
            boolean
        >;
    } catch {
        return {};
    }
}

/** Lembra, por navegador, quais cards a pessoa deixou expandidos. */
function useExpanded(id: string): [boolean, () => void] {
    const [expanded, setExpanded] = useState(() => Boolean(readExpanded()[id]));

    const toggle = () => {
        setExpanded((prev) => {
            const next = !prev;

            try {
                localStorage.setItem(
                    STORAGE_KEY,
                    JSON.stringify({ ...readExpanded(), [id]: next }),
                );
            } catch {
                // sem storage (aba privada): só não lembra
            }

            return next;
        });
    };

    return [expanded, toggle];
}

function Card({
    title,
    description,
    children,
    className = '',
}: {
    title: string;
    description?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-card border border-border bg-surface p-4 sm:p-5 ${className}`}
        >
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

/** Card de gráfico: compacto por padrão, com botão para expandir ao tamanho cheio. */
function ExpandableCard({
    id,
    title,
    description,
    className = '',
    children,
}: {
    id: string;
    title: string;
    description?: ReactNode;
    className?: string;
    children: (expanded: boolean) => ReactNode;
}) {
    const [expanded, toggle] = useExpanded(id);

    return (
        <section
            className={`rounded-card border border-border bg-surface p-4 sm:p-5 ${className}`}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="kicker text-faint">{title}</h2>
                    {description && (
                        <p className="mt-1 font-body text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={expanded}
                    className="inline-flex min-h-8 shrink-0 items-center rounded-md border border-border px-2.5 font-body text-xs font-semibold text-accent-text hover:bg-surface-2"
                >
                    {expanded ? 'Recolher' : 'Expandir'}
                </button>
            </div>
            <div className="mt-4">{children(expanded)}</div>
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="py-4 text-center font-body text-sm text-muted-foreground">
            {children}
        </p>
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
        <div className="min-w-0">
            <p className="tabular font-display text-display-sm text-foreground">
                <span className={tone}>{value}</span>
            </p>
            <p className="font-body text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

function Meter({ value, label }: { value: number; label: string }) {
    const pct = Math.min(100, Math.max(0, value));

    return (
        <div
            className="h-2 overflow-hidden rounded-full bg-surface-2"
            role="meter"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(pct)}
        >
            <div
                className="h-full rounded-full bg-accent"
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}

/** Barras horizontais com rótulo e valor (pagamentos, funil). */
function HBars({
    rows,
}: {
    rows: { key: string; label: string; value: number; display: ReactNode }[];
}) {
    const max = Math.max(1, ...rows.map((r) => r.value));

    return (
        <ul className="space-y-2.5">
            {rows.map((r) => (
                <li key={r.key}>
                    <div className="flex items-baseline justify-between gap-2 font-body text-xs">
                        <span className="text-foreground">{r.label}</span>
                        <span className="tabular text-muted-foreground">
                            {r.display}
                        </span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-surface-2">
                        <div
                            className="h-full rounded-full bg-accent"
                            style={{ width: `${(r.value / max) * 100}%` }}
                        />
                    </div>
                </li>
            ))}
        </ul>
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
    expanded,
}: {
    rows: {
        key: string;
        label: string;
        purchases: number;
        selections: number;
    }[];
    expanded: boolean;
}) {
    const max = Math.max(
        1,
        ...rows.flatMap((r) => [r.purchases, r.selections]),
    );
    const pct = (v: number) => `${(v / max) * 100}%`;

    return (
        <>
            {expanded && <Legend />}
            <div className="overflow-x-auto">
                <div
                    className={`flex items-end gap-1 ${expanded ? 'h-40 min-w-[560px]' : 'h-16'}`}
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
                {expanded && (
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
                )}
            </div>
        </>
    );
}

/** Colunas simples com valor em cima e rótulo embaixo. */
function Columns({
    rows,
    expanded,
}: {
    rows: { key: string; label: string; value: number; sub?: string }[];
    expanded: boolean;
}) {
    const max = Math.max(1, ...rows.map((r) => r.value));

    return (
        <div
            className={`flex items-end gap-2 ${expanded ? 'h-44' : 'h-24'}`}
            role="img"
            aria-label="Gráfico de colunas"
        >
            {rows.map((r) => (
                <div
                    key={r.key}
                    className="flex h-full min-w-0 flex-1 flex-col items-center justify-end"
                >
                    <span className="tabular font-body text-xs font-semibold text-foreground">
                        {r.value}
                    </span>
                    <div
                        className="mt-1 w-full max-w-16 rounded-t-md bg-accent"
                        style={{ height: `${(r.value / max) * 100}%` }}
                        title={
                            r.sub ? `${r.value} · ${r.sub}` : String(r.value)
                        }
                    />
                    <span className="mt-2 line-clamp-2 text-center font-body text-[11px] leading-tight text-muted-foreground">
                        {r.label}
                    </span>
                    {expanded && r.sub && (
                        <span className="tabular font-body text-[10px] text-faint">
                            {r.sub}
                        </span>
                    )}
                </div>
            ))}
        </div>
    );
}

/** Linha da receita acumulada em SVG. */
function CumulativeLine({
    rows,
    expanded,
}: {
    rows: OverviewInsightsData['revenueByDay'];
    expanded: boolean;
}) {
    const W = 600;
    const H = expanded ? 160 : 70;
    const max = Math.max(1, ...rows.map((r) => r.cumulative));
    const x = (i: number) =>
        rows.length === 1 ? W / 2 : (i / (rows.length - 1)) * W;
    const y = (v: number) => H - 4 - (v / max) * (H - 8);
    const points = rows.map((r, i) => `${x(i)},${y(r.cumulative)}`).join(' ');

    return (
        <div>
            <svg
                viewBox={`0 0 ${W} ${H}`}
                preserveAspectRatio="none"
                className="w-full text-accent"
                style={{ height: H }}
                role="img"
                aria-label="Receita acumulada por dia"
            >
                <polygon
                    points={`0,${H} ${points} ${W},${H}`}
                    fill="currentColor"
                    opacity={0.12}
                />
                <polyline
                    points={points}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2.5}
                    vectorEffect="non-scaling-stroke"
                />
                {rows.map((r, i) => (
                    <circle
                        key={r.date}
                        cx={x(i)}
                        cy={y(r.cumulative)}
                        r={3.5}
                        fill="currentColor"
                    >
                        <title>{`${formatDay(r.date)}: +R$ ${r.amount.toLocaleString('pt-BR')} · total R$ ${r.cumulative.toLocaleString('pt-BR')}`}</title>
                    </circle>
                ))}
            </svg>
            {expanded && (
                <ul className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 font-body text-xs sm:grid-cols-3">
                    {rows.map((r) => (
                        <li key={r.date} className="flex justify-between gap-2">
                            <span className="text-muted-foreground">
                                {formatDay(r.date)}
                            </span>
                            <span className="tabular text-foreground">
                                +<Price value={r.amount} />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

const formatDay = (iso: string) => {
    const [, m, d] = iso.split('-');

    return `${d}/${m}`;
};

const pctOf = (part: number, whole: number) =>
    whole > 0 ? Math.round((part / whole) * 100) : 0;

/** No dia do evento, atualiza o check-in sozinho a cada 30s (só com a aba visível). */
function useLiveCheckIn(live: boolean) {
    useEffect(() => {
        if (!live) {
            return;
        }

        const id = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['insights'] });
            }
        }, 30_000);

        return () => window.clearInterval(id);
    }, [live]);
}

export function OverviewInsights({ data }: { data: OverviewInsightsData }) {
    const {
        coupons,
        courtesies,
        activity,
        payments,
        funnel,
        failures,
        averages,
        revenueByDay,
        checkIn,
        signups,
    } = data;
    useLiveCheckIn(Boolean(checkIn?.live));

    const hasActivity = activity.byHour.some(
        (h) => h.purchases + h.selections > 0,
    );
    const peak = activity.byHour.reduce(
        (best, h) => (h.purchases > best.purchases ? h : best),
        activity.byHour[0] ?? { hour: 0, purchases: 0, selections: 0 },
    );
    const paidTotal = payments.reduce((s, p) => s + p.orders, 0);
    const totalRevenue = revenueByDay.at(-1)?.cumulative ?? 0;

    return (
        <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {checkIn && (
                <Card
                    title={checkIn.live ? 'Check-in · ao vivo' : 'Check-in'}
                    description={
                        checkIn.live
                            ? `${checkIn.session} · atualiza a cada 30s`
                            : `Próxima sessão: ${checkIn.session}`
                    }
                >
                    <div className="grid grid-cols-2 gap-3">
                        <Stat
                            label="Já entraram"
                            value={checkIn.checkedIn}
                            tone="text-accent-text"
                        />
                        <Stat
                            label="Faltam"
                            value={Math.max(
                                0,
                                checkIn.total - checkIn.checkedIn,
                            )}
                        />
                    </div>
                    <div className="mt-3">
                        <Meter
                            value={pctOf(checkIn.checkedIn, checkIn.total)}
                            label="Check-in"
                        />
                    </div>
                    <p className="mt-2 font-body text-xs text-muted-foreground">
                        {pctOf(checkIn.checkedIn, checkIn.total)}% de{' '}
                        {checkIn.total} ingresso(s)
                    </p>
                </Card>
            )}

            <Card
                title="Formas de pagamento"
                description={`${paidTotal} compra(s) paga(s)`}
            >
                {payments.length > 0 ? (
                    <HBars
                        rows={payments.map((p) => ({
                            key: p.method,
                            label: p.label,
                            value: p.orders,
                            display: (
                                <>
                                    {p.orders} · {pctOf(p.orders, paidTotal)}% ·{' '}
                                    <Price value={p.amount} />
                                </>
                            ),
                        }))}
                    />
                ) : (
                    <Empty>Nenhuma compra paga ainda.</Empty>
                )}
            </Card>

            <Card
                title="Ticket médio"
                description="Compras com valor (cortesia total não entra na média)."
            >
                <div className="grid grid-cols-2 gap-3">
                    <Stat
                        label="Por compra"
                        value={<Price value={averages.ticket} />}
                    />
                    <Stat
                        label="Ingressos por compra"
                        value={averages.ticketsPerOrder.toLocaleString(
                            'pt-BR',
                            { maximumFractionDigits: 1 },
                        )}
                    />
                </div>
            </Card>

            <Card
                title="Funil de conversão"
                description="Pessoas em cada etapa."
            >
                {funnel.selected > 0 ? (
                    <>
                        <HBars
                            rows={[
                                {
                                    key: 's',
                                    label: 'Escolheram lugar',
                                    value: funnel.selected,
                                    display: funnel.selected,
                                },
                                {
                                    key: 'o',
                                    label: 'Criaram pedido',
                                    value: funnel.ordered,
                                    display: `${funnel.ordered} · ${pctOf(funnel.ordered, funnel.selected)}%`,
                                },
                                {
                                    key: 'p',
                                    label: 'Pagaram',
                                    value: funnel.paid,
                                    display: `${funnel.paid} · ${pctOf(funnel.paid, funnel.selected)}%`,
                                },
                            ]}
                        />
                        <p className="mt-3 font-body text-xs text-muted-foreground">
                            {Math.max(0, funnel.selected - funnel.paid)}{' '}
                            pessoa(s) escolheram lugar e não pagaram.
                        </p>
                    </>
                ) : (
                    <Empty>Sem movimento ainda.</Empty>
                )}
            </Card>

            <Card
                title="Recusas e Pix expirados"
                description={
                    failures.people > 0
                        ? `${failures.recovered} de ${failures.people} pessoa(s) acabaram comprando depois.`
                        : undefined
                }
            >
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Stat
                            label="Cartões recusados"
                            value={failures.cardRejected}
                        />
                        <p className="tabular mt-0.5 font-body text-xs text-faint">
                            <Price value={failures.cardRejectedAmount} />
                        </p>
                    </div>
                    <div>
                        <Stat
                            label="Pix não pagos"
                            value={failures.pixExpired}
                        />
                        <p className="tabular mt-0.5 font-body text-xs text-faint">
                            <Price value={failures.pixExpiredAmount} />
                        </p>
                    </div>
                </div>
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
                                label="Expiradas"
                                value={courtesies.expired}
                            />
                        </div>
                        <div className="mt-3">
                            <Meter
                                value={pctOf(
                                    courtesies.redeemed,
                                    courtesies.total,
                                )}
                                label="Cortesias resgatadas"
                            />
                        </div>
                        <p className="mt-2 font-body text-xs text-muted-foreground">
                            {courtesies.ticketsRedeemed} ingresso(s) grátis
                            emitidos · {courtesies.ticketsAvailable} a resgatar
                        </p>
                    </>
                ) : (
                    <Empty>Nenhuma cortesia criada.</Empty>
                )}
            </Card>

            <ExpandableCard
                id="coupons"
                title="Compras com cupom"
                description={`${coupons.ordersWithCoupon} de ${coupons.paidOrders} compras pagas · ${coupons.ticketsWithCoupon} ingressos`}
            >
                {(expanded) =>
                    coupons.byKind.length > 0 ? (
                        <Columns
                            expanded={expanded}
                            rows={coupons.byKind.map((k) => ({
                                key: k.label,
                                label: k.label,
                                value: k.orders,
                                sub: `${k.tickets} ingr.`,
                            }))}
                        />
                    ) : (
                        <Empty>Nenhuma compra paga com cupom ainda.</Empty>
                    )
                }
            </ExpandableCard>

            <ExpandableCard
                id="revenue"
                title="Receita acumulada"
                description={
                    <>
                        <Price value={totalRevenue} /> em {revenueByDay.length}{' '}
                        dia(s) com venda
                    </>
                }
                className="md:col-span-2"
            >
                {(expanded) =>
                    revenueByDay.length > 0 ? (
                        <CumulativeLine
                            rows={revenueByDay}
                            expanded={expanded}
                        />
                    ) : (
                        <Empty>Nenhuma venda paga ainda.</Empty>
                    )
                }
            </ExpandableCard>

            <ExpandableCard
                id="peaks"
                title="Horários de pico"
                description={
                    hasActivity && peak.purchases > 0
                        ? `Pico de compras às ${String(peak.hour).padStart(2, '0')}h (${peak.purchases}). Soma de todos os dias.`
                        : 'Por hora do dia, somando todos os dias.'
                }
                className="md:col-span-2 xl:col-span-3"
            >
                {(expanded) =>
                    hasActivity ? (
                        <PairedColumns
                            expanded={expanded}
                            rows={activity.byHour.map((h) => ({
                                key: String(h.hour),
                                label: `${String(h.hour).padStart(2, '0')}h`,
                                purchases: h.purchases,
                                selections: h.selections,
                            }))}
                        />
                    ) : (
                        <Empty>Sem movimento ainda.</Empty>
                    )
                }
            </ExpandableCard>

            <ExpandableCard
                id="days"
                title="Movimento por data"
                description="Compras pagas x escolhas de lugar, últimos dias com movimento."
                className="md:col-span-2"
            >
                {(expanded) =>
                    activity.byDay.length > 0 ? (
                        <PairedColumns
                            expanded={expanded}
                            rows={activity.byDay.map((d) => ({
                                key: d.date,
                                label: formatDay(d.date),
                                purchases: d.purchases,
                                selections: d.selections,
                            }))}
                        />
                    ) : (
                        <Empty>Sem movimento ainda.</Empty>
                    )
                }
            </ExpandableCard>

            <ExpandableCard
                id="signups"
                title="Novos cadastros"
                description={`${signups.reduce((s, d) => s + d.count, 0)} conta(s) nos últimos dias com cadastro`}
            >
                {(expanded) =>
                    signups.length > 0 ? (
                        <Columns
                            expanded={expanded}
                            rows={signups.map((d) => ({
                                key: d.date,
                                label: formatDay(d.date),
                                value: d.count,
                            }))}
                        />
                    ) : (
                        <Empty>Nenhum cadastro ainda.</Empty>
                    )
                }
            </ExpandableCard>
        </div>
    );
}
