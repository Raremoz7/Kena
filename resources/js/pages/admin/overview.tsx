import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import type { IconName } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { AdminEventsTable } from '@/components/organisms/AdminEventsTable';
import type { EventRow } from '@/components/organisms/AdminEventsTable';

interface Kpis {
    events: number;
    sessions: number;
    capacity: number;
    sold: number;
    available: number;
    occupancy: number;
    revenue: number;
}

function Metric({
    label,
    value,
    hint,
    children,
}: {
    label: string;
    value: ReactNode;
    hint?: string;
    children?: ReactNode;
}) {
    return (
        <div className="h-full rounded-card border border-border bg-surface p-4 sm:p-5">
            <p className="kicker text-faint">{label}</p>
            <p className="tabular mt-2 font-display text-display-sm text-foreground sm:text-display-md">
                {value}
            </p>
            {children}
            {hint && (
                <p className="mt-1 font-body text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
        </div>
    );
}

const shortcuts: { label: string; href: string; icon: IconName }[] = [
    { label: 'Novo evento', href: '/painel/eventos/novo', icon: 'plus' },
    { label: 'Check-in', href: '/painel/checkin', icon: 'qr' },
    { label: 'Cupons', href: '/painel/cupons', icon: 'tag' },
    { label: 'Cortesias', href: '/painel/cortesias', icon: 'gift' },
];

export default function Overview({
    kpis,
    events,
}: {
    kpis: Kpis;
    events: EventRow[];
}) {
    const occupancy = Math.min(100, Math.max(0, kpis.occupancy));

    return (
        <>
            <Head title="Painel — Visão geral" />
            <AdminPage>
                <PageHeader
                    title="Visão geral"
                    description="Resumo de vendas e eventos do Kena."
                />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div className="col-span-2 lg:col-span-1">
                        <Metric
                            label="Receita"
                            value={<Price value={kpis.revenue} />}
                            hint="Só o que foi pago (cortesias não contam)"
                        />
                    </div>
                    <Metric label="Vendidos" value={kpis.sold}>
                        <div
                            className="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-2"
                            role="meter"
                            aria-label="Ocupação"
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={occupancy}
                        >
                            <div
                                className="h-full rounded-full bg-accent"
                                style={{ width: `${occupancy}%` }}
                            />
                        </div>
                        <p className="mt-1.5 font-body text-xs text-muted-foreground">
                            {kpis.occupancy}% de ocupação
                        </p>
                    </Metric>
                    <Metric label="Disponíveis" value={kpis.available} />
                    <div className="col-span-2 lg:col-span-1">
                        <Metric
                            label="Capacidade"
                            value={kpis.capacity}
                            hint={`${kpis.events} evento(s) · ${kpis.sessions} sessão(ões)`}
                        />
                    </div>
                </div>

                <nav
                    aria-label="Atalhos"
                    className="mt-6 grid grid-cols-4 gap-2 md:hidden"
                >
                    {shortcuts.map((s) => (
                        <Link
                            key={s.href}
                            href={s.href}
                            className="flex flex-col items-center gap-1.5 rounded-card border border-border bg-surface px-1 py-3 text-center font-body text-[11px] leading-tight font-medium text-foreground transition-transform active:scale-95"
                        >
                            <span className="flex size-9 items-center justify-center rounded-full bg-accent/10 text-accent-text">
                                <Icon name={s.icon} size={20} />
                            </span>
                            {s.label}
                        </Link>
                    ))}
                </nav>

                <section className="mt-8 sm:mt-10">
                    <div className="flex items-center justify-between">
                        <h2 className="kicker text-faint">Eventos</h2>
                        <Link
                            href="/painel/eventos"
                            className="inline-flex min-h-9 items-center font-body text-xs font-semibold text-accent-text hover:underline"
                        >
                            Ver todos
                        </Link>
                    </div>
                    <div className="mt-2">
                        <AdminEventsTable events={events} />
                    </div>
                </section>
            </AdminPage>
        </>
    );
}
