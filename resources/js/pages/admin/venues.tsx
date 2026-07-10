import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
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

export default function AdminVenues({ venues }: { venues: Paginator<VenueRow> }) {
    const [removing, setRemoving] = useState<VenueRow | null>(null);

    return (
        <>
            <Head title="Painel — Locais" />
            <div className="px-6 py-8 sm:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="font-display text-display-lg text-foreground uppercase">Locais</h1>
                        <p className="mt-1 font-body text-sm text-muted-foreground">
                            Teatros e espaços — nome, endereço e mapa de assentos.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/locais/novo">
                            <Icon name="plus" size={18} /> Novo local
                        </Link>
                    </Button>
                </div>

                <div className="mt-6 overflow-hidden rounded-card border border-border">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">Local</th>
                                <th className="kicker px-4 py-3 text-faint">Cidade</th>
                                <th className="kicker px-4 py-3 text-right text-faint">Assentos</th>
                                <th className="kicker px-4 py-3 text-right text-faint">Eventos</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {venues.data.map((v) => (
                                <tr key={v.id} className="border-b border-border last:border-b-0 hover:bg-surface-2">
                                    <td className="px-4 py-3 font-medium text-foreground">{v.name}</td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {v.city} · {v.state}
                                    </td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">{v.seats}</td>
                                    <td className="px-4 py-3 text-right text-muted-foreground tabular-nums">{v.events}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button asChild size="sm" variant="ghost">
                                                <Link href={`/dashboard/locais/${v.id}/editar`}>
                                                    <Icon name="eye" size={15} /> Editar
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
                                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                        Nenhum local cadastrado ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={venues.links} />
            </div>

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
                        router.delete(`/dashboard/locais/${removing.id}`, {
                            preserveScroll: true,
                        });
                    }
                    setRemoving(null);
                }}
            />
        </>
    );
}
