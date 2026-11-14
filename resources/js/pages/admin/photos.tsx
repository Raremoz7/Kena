import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { EmptyState } from '@/components/molecules/EmptyState';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';

interface EventRow {
    id: number;
    title: string;
    hasGallery: boolean;
    status: 'draft' | 'published' | null;
    photoCount: number;
    peopleCount: number;
    groupCount: number;
    fromPrice: number | null;
    sales: number | null;
    publicUrl: string | null;
}

function StatusBadge({ row }: { row: EventRow }) {
    if (!row.hasGallery) {
        return <Badge tone="neutral">Sem galeria</Badge>;
    }

    return row.status === 'published' ? (
        <Badge tone="success">Publicada</Badge>
    ) : (
        <Badge tone="warning">Rascunho</Badge>
    );
}

function Stat({ value, label }: { value: string | number; label: string }) {
    return (
        <div>
            <p className="font-display text-display-sm text-foreground tabular-nums">
                {value}
            </p>
            <p className="font-body text-xs text-faint">{label}</p>
        </div>
    );
}

function brl(value: number): string {
    return value.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export default function AdminPhotos({ events }: { events: EventRow[] }) {
    return (
        <>
            <Head title="Painel — Fotos" />
            <AdminPage>
                <PageHeader
                    title="Fotos"
                    description="Uma galeria por evento. As fotos entram pelo importador; aqui você define preço, confere e publica."
                />

                {events.length === 0 ? (
                    <div className="rounded-card border border-border py-14">
                        <EmptyState
                            icon="image"
                            title="Nenhum evento ainda"
                            description="Crie um evento para montar a galeria de fotos dele."
                        />
                    </div>
                ) : (
                    <ul className="grid gap-4 lg:grid-cols-2">
                        {events.map((row) => (
                            <li
                                key={row.id}
                                className="flex flex-col gap-5 rounded-card border border-border bg-surface p-4 sm:p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="font-display text-display-sm break-words text-foreground uppercase">
                                            {row.title}
                                        </h2>
                                        {row.fromPrice !== null && (
                                            <p className="mt-1 font-body text-sm text-muted-foreground">
                                                A partir de{' '}
                                                {brl(row.fromPrice)} por foto
                                            </p>
                                        )}
                                    </div>
                                    <StatusBadge row={row} />
                                </div>

                                {row.hasGallery ? (
                                    <dl className="grid grid-cols-4 gap-3 border-y border-border py-4">
                                        <Stat
                                            value={row.photoCount}
                                            label="fotos"
                                        />
                                        <Stat
                                            value={row.peopleCount}
                                            label="pessoas"
                                        />
                                        <Stat
                                            value={row.groupCount}
                                            label="turmas"
                                        />
                                        {row.sales === null ? (
                                            <div>
                                                <p className="font-display text-display-sm text-faint">
                                                    —
                                                </p>
                                                <p className="font-body text-xs text-faint">
                                                    vendas, em breve
                                                </p>
                                            </div>
                                        ) : (
                                            <Stat
                                                value={row.sales}
                                                label="vendas"
                                            />
                                        )}
                                    </dl>
                                ) : (
                                    <p className="border-y border-border py-4 font-body text-sm text-muted-foreground">
                                        Este evento ainda não tem galeria de
                                        fotos.
                                    </p>
                                )}

                                <div className="flex flex-wrap items-center gap-2">
                                    {row.hasGallery ? (
                                        <>
                                            <Button asChild size="sm">
                                                <Link
                                                    href={`/painel/fotos/${row.id}/galeria`}
                                                >
                                                    <Icon
                                                        name="image"
                                                        size={15}
                                                    />
                                                    Conferir fotos
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="secondary"
                                            >
                                                <Link
                                                    href={`/painel/fotos/${row.id}/pessoas`}
                                                >
                                                    <Icon
                                                        name="users"
                                                        size={15}
                                                    />
                                                    Pessoas
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="secondary"
                                            >
                                                <Link
                                                    href={`/painel/fotos/${row.id}`}
                                                >
                                                    <Icon
                                                        name="settings"
                                                        size={15}
                                                    />
                                                    Ajustes
                                                </Link>
                                            </Button>
                                        </>
                                    ) : (
                                        <Button asChild size="sm">
                                            <Link
                                                href={`/painel/fotos/${row.id}`}
                                            >
                                                <Icon name="plus" size={15} />
                                                Criar galeria
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </AdminPage>
        </>
    );
}
