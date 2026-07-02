import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';

interface VenueRow {
    id: number;
    name: string;
    city: string;
    state: string;
    seats: number;
    events: number;
}

export default function AdminVenues({ venues }: { venues: VenueRow[] }) {
    function remove(v: VenueRow) {
        if (v.events > 0) {
            return;
        }

        if (window.confirm(`Excluir o local "${v.name}"?`)) {
            router.delete(`/dashboard/locais/${v.id}`, { preserveScroll: true });
        }
    }

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
                            {venues.map((v) => (
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
                                                onClick={() => remove(v)}
                                                aria-label={`Excluir ${v.name}`}
                                            >
                                                <Icon name="trash" size={15} />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {venues.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                        Nenhum local cadastrado ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
