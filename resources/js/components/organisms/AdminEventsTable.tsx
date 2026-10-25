import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import type { BadgeProps } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import {
    MobileList,
    MobileListEmpty,
    MobileListItem,
} from '@/components/molecules/MobileList';

export interface EventRow {
    id: number;
    slug: string;
    title: string;
    status: string;
    venue: string;
    sessionsCount: number;
    nextDate: string | null;
    capacity: number;
    sold: number;
}

const tone: Record<string, NonNullable<BadgeProps['tone']>> = {
    on_sale: 'success',
    sold_out: 'warning',
    draft: 'neutral',
};

const label: Record<string, string> = {
    on_sale: 'À venda',
    sold_out: 'Esgotado',
    draft: 'Rascunho',
};

function Occupancy({ sold, capacity }: { sold: number; capacity: number }) {
    const percent =
        capacity > 0 ? Math.min(100, Math.round((sold / capacity) * 100)) : 0;

    return (
        <div className="min-w-[7rem]">
            <p className="font-body text-sm text-muted-foreground tabular-nums">
                {sold}
                <span className="text-faint">/{capacity}</span>
            </p>
            <div className="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-surface-2">
                <div
                    className="h-full rounded-full bg-accent"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

export function AdminEventsTable({
    events,
    editable = false,
}: {
    events: EventRow[];
    editable?: boolean;
}) {
    const [removing, setRemoving] = useState<EventRow | null>(null);

    const emptyText = (
        <>
            Nenhum evento ainda.{' '}
            {editable && (
                <Link
                    href="/painel/eventos/novo"
                    className="font-semibold text-accent-text hover:underline"
                >
                    Criar o primeiro
                </Link>
            )}
        </>
    );

    return (
        <>
            <MobileList>
                {events.map((e) => (
                    <MobileListItem
                        key={e.id}
                        href={
                            editable
                                ? `/painel/eventos/${e.id}/editar`
                                : `/e/${e.slug}`
                        }
                        title={e.title}
                        subtitle={e.venue}
                        meta={
                            <span className="tabular-nums">
                                {e.nextDate ?? 'Sem sessão'} · {e.sold}/
                                {e.capacity} vendidos
                            </span>
                        }
                        aside={
                            <Badge tone={tone[e.status] ?? 'neutral'}>
                                {label[e.status] ?? e.status}
                            </Badge>
                        }
                        actions={
                            editable ? (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setRemoving(e)}
                                    aria-label={`Excluir ${e.title}`}
                                    className="text-faint hover:text-danger-text"
                                >
                                    <Icon name="trash" size={15} /> Excluir
                                </Button>
                            ) : undefined
                        }
                    />
                ))}
                {events.length === 0 && (
                    <MobileListEmpty>{emptyText}</MobileListEmpty>
                )}
            </MobileList>

            <div className="hidden overflow-x-auto rounded-card border border-border md:block">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-border bg-surface-2">
                            <th className="kicker px-4 py-3 font-normal text-faint">
                                Evento
                            </th>
                            <th className="kicker px-4 py-3 font-normal text-faint">
                                Sessão
                            </th>
                            <th className="kicker px-4 py-3 font-normal text-faint">
                                Ocupação
                            </th>
                            <th className="kicker px-4 py-3 font-normal text-faint">
                                Status
                            </th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {events.map((e) => (
                            <tr
                                key={e.id}
                                className="border-b border-border last:border-0 hover:bg-surface-2"
                            >
                                <td className="px-4 py-3">
                                    <p className="font-body text-sm font-medium text-foreground">
                                        {e.title}
                                    </p>
                                    <p className="font-body text-xs text-faint">
                                        {e.venue}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <p className="tabular font-body text-sm text-muted-foreground">
                                        {e.nextDate ?? '—'}
                                    </p>
                                    <p className="font-body text-xs text-faint">
                                        {e.sessionsCount} sessão(ões)
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <Occupancy
                                        sold={e.sold}
                                        capacity={e.capacity}
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    <Badge tone={tone[e.status] ?? 'neutral'}>
                                        {label[e.status] ?? e.status}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {editable ? (
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={`/painel/eventos/${e.id}/editar`}
                                                >
                                                    Editar
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                aria-label={`Excluir ${e.title}`}
                                                onClick={() => setRemoving(e)}
                                                className="text-faint hover:text-danger-text"
                                            >
                                                <Icon name="trash" size={15} />
                                            </Button>
                                        </div>
                                    ) : (
                                        <Link
                                            href={`/e/${e.slug}`}
                                            className="inline-flex items-center gap-1 font-body text-xs font-semibold text-accent-text hover:underline"
                                        >
                                            Ver{' '}
                                            <Icon
                                                name="arrow-right"
                                                size={13}
                                            />
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {events.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-10 text-center font-body text-sm text-muted-foreground"
                                >
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Excluir evento"
                description={
                    removing
                        ? `"${removing.title}" será excluído. Esta ação não pode ser desfeita.`
                        : ''
                }
                confirmLabel="Excluir"
                onConfirm={() => {
                    if (removing) {
                        router.delete(`/painel/eventos/${removing.id}`);
                    }

                    setRemoving(null);
                }}
            />
        </>
    );
}
