import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge  } from '@/components/atoms/Badge';
import type {BadgeProps} from '@/components/atoms/Badge';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';

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

export function AdminEventsTable({
    events,
    editable = false,
}: {
    events: EventRow[];
    editable?: boolean;
}) {
    const [removing, setRemoving] = useState<EventRow | null>(null);

    return (
        <div className="overflow-x-auto rounded-card border border-border">
            <table className="w-full text-left">
                <thead>
                    <tr className="border-b border-border bg-surface-2">
                        <th className="kicker px-4 py-3 font-normal text-faint">Evento</th>
                        <th className="kicker px-4 py-3 font-normal text-faint">Sessão</th>
                        <th className="kicker px-4 py-3 font-normal text-faint">Ocupação</th>
                        <th className="kicker px-4 py-3 font-normal text-faint">Status</th>
                        <th className="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    {events.map((e) => (
                        <tr key={e.id} className="border-b border-border last:border-0">
                            <td className="px-4 py-3">
                                <p className="font-body text-sm font-medium text-foreground">{e.title}</p>
                                <p className="font-body text-xs text-faint">{e.venue}</p>
                            </td>
                            <td className="px-4 py-3">
                                <p className="font-body text-sm text-muted-foreground tabular">
                                    {e.nextDate ?? '—'}
                                </p>
                                <p className="font-body text-xs text-faint">{e.sessionsCount} sessão(ões)</p>
                            </td>
                            <td className="px-4 py-3 font-body text-sm text-muted-foreground tabular">
                                {e.sold}/{e.capacity}
                            </td>
                            <td className="px-4 py-3">
                                <Badge tone={tone[e.status] ?? 'neutral'}>{label[e.status] ?? e.status}</Badge>
                            </td>
                            <td className="px-4 py-3 text-right">
                                {editable ? (
                                    <div className="flex items-center justify-end gap-4">
                                        <Link
                                            href={`/dashboard/eventos/${e.id}/editar`}
                                            className="font-body text-xs font-semibold text-accent-text hover:underline"
                                        >
                                            Editar
                                        </Link>
                                        <button
                                            type="button"
                                            aria-label={`Excluir ${e.title}`}
                                            onClick={() => setRemoving(e)}
                                            className="text-faint transition-colors hover:text-danger-text"
                                        >
                                            <Icon name="trash" size={15} />
                                        </button>
                                    </div>
                                ) : (
                                    <Link
                                        href={`/e/${e.slug}`}
                                        className="inline-flex items-center gap-1 font-body text-xs font-semibold text-accent-text hover:underline"
                                    >
                                        Ver <Icon name="arrow-right" size={13} />
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
                                Nenhum evento ainda.{' '}
                                {editable && (
                                    <Link
                                        href="/dashboard/eventos/novo"
                                        className="font-semibold text-accent-text hover:underline"
                                    >
                                        Criar o primeiro
                                    </Link>
                                )}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>

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
                        router.delete(`/dashboard/eventos/${removing.id}`);
                    }
                    setRemoving(null);
                }}
            />
        </div>
    );
}
