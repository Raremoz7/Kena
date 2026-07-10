import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Price } from '@/components/atoms/Price';
import { AdminEventsTable  } from '@/components/organisms/AdminEventsTable';
import type {EventRow} from '@/components/organisms/AdminEventsTable';

interface Kpis {
    events: number;
    sessions: number;
    capacity: number;
    sold: number;
    available: number;
    occupancy: number;
    revenue: number;
}

function Metric({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
    return (
        <div className="rounded-card border border-border bg-surface p-5">
            <p className="kicker text-faint">{label}</p>
            <p className="mt-2 font-display text-display-md text-foreground tabular">{value}</p>
            {hint && <p className="mt-1 font-body text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

export default function Overview({ kpis, events }: { kpis: Kpis; events: EventRow[] }) {
    return (
        <>
            <Head title="Painel — Visão geral" />
            <div className="px-6 py-8 sm:px-8">
                <h1 className="font-display text-display-lg text-foreground uppercase">Visão geral</h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Resumo de vendas e eventos do Kena.
                </p>

                <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Metric label="Receita" value={<Price value={kpis.revenue} />} />
                    <Metric
                        label="Vendidos"
                        value={kpis.sold}
                        hint={`${kpis.occupancy}% de ocupação`}
                    />
                    <Metric label="Disponíveis" value={kpis.available} />
                    <Metric
                        label="Capacidade"
                        value={kpis.capacity}
                        hint={`${kpis.events} evento(s) · ${kpis.sessions} sessão(ões)`}
                    />
                </div>

                <div className="mt-10">
                    <div className="flex items-center justify-between">
                        <h2 className="kicker text-faint">Eventos</h2>
                        <Link
                            href="/dashboard/eventos"
                            className="font-body text-xs font-semibold text-accent-text hover:underline"
                        >
                            Ver todos
                        </Link>
                    </div>
                    <div className="mt-3">
                        <AdminEventsTable events={events} />
                    </div>
                </div>
            </div>
        </>
    );
}
