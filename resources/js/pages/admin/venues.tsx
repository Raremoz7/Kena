import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import {
    MobileList,
    MobileListEmpty,
    MobileListItem,
} from '@/components/molecules/MobileList';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';

interface VenueRow {
    id: number;
    name: string;
    city: string;
    state: string;
    seats: number;
    events: number;
}

export default function AdminVenues({
    venues,
}: {
    venues: Paginator<VenueRow>;
}) {
    const [removing, setRemoving] = useState<VenueRow | null>(null);

    return (
        <>
            <Head title="Painel — Locais" />
            <AdminPage>
                <PageHeader
                    title="Locais"
                    description="Teatros e espaços — nome, endereço e mapa de assentos."
                    primary={{
                        label: 'Novo local',
                        href: '/painel/locais/novo',
                    }}
                />

                <MobileList>
                    {venues.data.map((v) => (
                        <MobileListItem
                            key={v.id}
                            href={`/painel/locais/${v.id}/editar`}
                            title={v.name}
                            subtitle={`${v.city} · ${v.state}`}
                            meta={
                                <span className="tabular-nums">
                                    {v.seats} assentos · {v.events} evento(s)
                                </span>
                            }
                            actions={
                                v.events === 0 ? (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setRemoving(v)}
                                        className="text-faint hover:text-danger-text"
                                    >
                                        <Icon name="trash" size={15} /> Excluir
                                    </Button>
                                ) : undefined
                            }
                        />
                    ))}
                    {venues.data.length === 0 && (
                        <MobileListEmpty>
                            Nenhum local cadastrado ainda.
                        </MobileListEmpty>
                    )}
                </MobileList>

                <div className="hidden overflow-hidden rounded-card border border-border md:block">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">
                                    Local
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Cidade
                                </th>
                                <th className="kicker px-4 py-3 text-right text-faint">
                                    Assentos
                                </th>
                                <th className="kicker px-4 py-3 text-right text-faint">
                                    Eventos
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {venues.data.map((v) => (
                                <tr
                                    key={v.id}
                                    className="border-b border-border last:border-b-0 hover:bg-surface-2"
                                >
                                    <td className="px-4 py-3 font-medium text-foreground">
                                        {v.name}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {v.city} · {v.state}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {v.seats}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">
                                        {v.events}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <Link
                                                    href={`/painel/locais/${v.id}/editar`}
                                                >
                                                    <Icon
                                                        name="eye"
                                                        size={15}
                                                    />{' '}
                                                    Editar
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={v.events > 0}
                                                onClick={() => setRemoving(v)}
                                                aria-label={`Excluir ${v.name}`}
                                            >
                                                <Icon name="trash" size={15} />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {venues.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Nenhum local cadastrado ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={venues.links} />
            </AdminPage>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Excluir local"
                description={
                    removing ? `O local "${removing.name}" será excluído.` : ''
                }
                confirmLabel="Excluir"
                onConfirm={() => {
                    if (removing && removing.events === 0) {
                        router.delete(`/painel/locais/${removing.id}`, {
                            preserveScroll: true,
                        });
                    }

                    setRemoving(null);
                }}
            />
        </>
    );
}
