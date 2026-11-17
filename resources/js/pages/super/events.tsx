import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { brl, Filters, Pager } from '@/components/organisms/SuperAdminList';
import type { SimplePage } from '@/components/organisms/SuperAdminList';

interface EventRow {
    id: number;
    title: string;
    slug: string;
    status: string;
    organization: { id: number; name: string } | null;
    venue: string;
    nextDate: string | null;
    capacity: number;
    sold: number;
    gross: number;
}

interface EventsProps {
    events: SimplePage<EventRow>;
    filters: { q: string; organizacao: number | null };
    organizations: { id: number; name: string }[];
}

const statusLabel: Record<string, string> = {
    draft: 'Rascunho',
    on_sale: 'À venda',
    sold_out: 'Esgotado',
};

/** Super admin: eventos de todos os organizadores. */
export default function SuperEvents({
    events,
    filters,
    organizations,
}: EventsProps) {
    return (
        <>
            <Head title="Admin — Eventos" />
            <AdminPage>
                <PageHeader
                    title="Eventos"
                    description={`${events.meta.total} eventos de todos os organizadores. Para editar, entre como o organizador.`}
                />
                <Filters
                    url="/admin/eventos"
                    q={filters.q}
                    placeholder="Evento ou organização"
                    organizations={organizations}
                    organizationId={filters.organizacao}
                />

                <ul className="mt-4 flex flex-col gap-3">
                    {events.data.map((e) => (
                        <li
                            key={e.id}
                            className="rounded-card border border-border bg-surface p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <a
                                        href={`/e/${e.slug}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-body text-base font-medium text-foreground hover:underline"
                                    >
                                        {e.title}
                                    </a>
                                    <p className="font-body text-xs text-muted-foreground">
                                        {e.organization?.name ?? '—'} ·{' '}
                                        {e.venue || '—'}
                                        {e.nextDate && ` · ${e.nextDate}`}
                                    </p>
                                </div>
                                <Badge
                                    tone={
                                        e.status === 'on_sale'
                                            ? 'success'
                                            : 'neutral'
                                    }
                                >
                                    {statusLabel[e.status] ?? e.status}
                                </Badge>
                            </div>
                            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <p className="font-body text-xs text-muted-foreground tabular-nums">
                                    {e.sold}/{e.capacity} lugares vendidos ·{' '}
                                    {brl(e.gross)} em pedidos pagos
                                </p>
                                {e.organization && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        onClick={() =>
                                            router.post(
                                                `/admin/organizacoes/${e.organization?.id}/entrar`,
                                            )
                                        }
                                    >
                                        Entrar como organizador
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                    {events.data.length === 0 && (
                        <li className="font-body text-sm text-muted-foreground">
                            Nenhum evento encontrado.
                        </li>
                    )}
                </ul>
                <Pager page={events} />
            </AdminPage>
        </>
    );
}
