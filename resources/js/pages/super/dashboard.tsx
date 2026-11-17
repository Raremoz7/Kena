import { Head, router } from '@inertiajs/react';
import { Select } from '@/components/atoms/Select';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';

interface Kpis {
    orders: number;
    gross: number;
    fees: number;
    refunded: number;
    organizations: number;
    activeOrganizations: number;
}

interface OrgRow {
    id: number;
    name: string;
    orders: number;
    gross: number;
    fees: number;
}

interface Month {
    month: string;
    label: string;
    gross: number;
    fees: number;
}

interface DashboardProps {
    period: string;
    periods: { value: string; label: string }[];
    kpis: Kpis;
    byOrganization: OrgRow[];
    months: Month[];
}

const brl = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function Kpi({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="rounded-card border border-border bg-surface p-4">
            <p className="kicker text-faint">{label}</p>
            <p className="mt-1 font-display text-display-sm text-foreground">
                {value}
            </p>
            {hint && <p className="font-body text-[11px] text-faint">{hint}</p>}
        </div>
    );
}

/** Visão financeira da plataforma: todos os organizadores. */
export default function SuperDashboard({
    period,
    periods,
    kpis,
    byOrganization,
    months,
}: DashboardProps) {
    const maxGross = Math.max(1, ...months.map((m) => m.gross));

    return (
        <>
            <Head title="Admin — Visão geral" />
            <AdminPage>
                <PageHeader
                    title="Visão geral"
                    description="Vendas e taxas de todos os organizadores. Valores de pedidos pagos, antes de estornos."
                />

                <div className="max-w-xs">
                    <label htmlFor="periodo" className="sr-only">
                        Período
                    </label>
                    <Select
                        id="periodo"
                        value={period}
                        onChange={(e) =>
                            router.get(
                                '/admin',
                                { periodo: e.target.value },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {periods.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </Select>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Kpi label="Vendido" value={brl(kpis.gross)} />
                    <Kpi label="Taxa da plataforma" value={brl(kpis.fees)} />
                    <Kpi label="Pedidos pagos" value={String(kpis.orders)} />
                    <Kpi
                        label="Estornado"
                        value={brl(kpis.refunded)}
                        hint="Estornos concluídos no período."
                    />
                </div>
                <p className="mt-2 font-body text-xs text-faint">
                    {kpis.activeOrganizations} de {kpis.organizations}{' '}
                    organizações ativas.
                </p>

                <section className="mt-8">
                    <h2 className="kicker text-faint">Últimos 12 meses</h2>
                    <ul className="mt-3 flex flex-col gap-2 rounded-card border border-border bg-surface p-4">
                        {months.map((m) => (
                            <li
                                key={m.month}
                                className="grid grid-cols-[3.5rem_1fr_auto] items-center gap-3 font-body text-xs"
                            >
                                <span className="text-muted-foreground capitalize">
                                    {m.label}
                                </span>
                                <span
                                    className="h-2.5 rounded-full bg-surface-2"
                                    aria-hidden="true"
                                >
                                    <span
                                        className="block h-full rounded-full bg-accent"
                                        style={{
                                            width: `${(m.gross / maxGross) * 100}%`,
                                        }}
                                    />
                                </span>
                                <span className="text-right text-foreground tabular-nums">
                                    {brl(m.gross)}{' '}
                                    <span className="text-faint">
                                        · taxa {brl(m.fees)}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="mt-8">
                    <h2 className="kicker text-faint">
                        Por organização no período
                    </h2>
                    {byOrganization.length === 0 ? (
                        <p className="mt-3 font-body text-sm text-muted-foreground">
                            Nenhuma venda paga no período.
                        </p>
                    ) : (
                        <ul className="mt-3 divide-y divide-border rounded-card border border-border bg-surface">
                            {byOrganization.map((o) => (
                                <li
                                    key={o.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 font-body text-sm"
                                >
                                    <span className="font-medium text-foreground">
                                        {o.name}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {o.orders} pedidos · {brl(o.gross)} ·
                                        taxa {brl(o.fees)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </AdminPage>
        </>
    );
}
