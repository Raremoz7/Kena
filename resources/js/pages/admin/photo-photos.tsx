import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { EmptyState } from '@/components/molecules/EmptyState';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';
import type { DriveState } from '@/components/organisms/photos/DriveImportSection';
import { PhotoGalleryShell } from '@/components/organisms/photos/PhotoGalleryShell';
import type { GalleryShellData } from '@/components/organisms/photos/PhotoGalleryShell';
import { cn } from '@/lib/utils';

interface PhotoRow {
    id: number;
    filename: string;
    thumb: string;
    group: string | null;
    people: string[];
}

interface Group {
    id: number;
    name: string;
    count: number;
}

function Chip({
    href,
    active,
    children,
}: {
    href: string;
    active: boolean;
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            preserveScroll
            aria-current={active ? 'true' : undefined}
            className={cn(
                'inline-flex min-h-9 items-center gap-1.5 rounded-badge border px-3 font-body text-sm whitespace-nowrap transition-colors',
                active
                    ? 'border-accent bg-accent text-accent-fg'
                    : 'border-border-strong text-muted-foreground hover:bg-surface-2 hover:text-foreground',
            )}
        >
            {children}
        </Link>
    );
}

const isRunning = (drive: DriveState) =>
    drive.status === 'queued' || drive.status === 'running';

/**
 * Galeria vazia: importa do Drive ali mesmo. Sem pasta salva (ou sem Drive
 * configurado), leva aos ajustes, onde se cola o link da pasta.
 */
function EmptyImport({ eventId, drive }: { eventId: number; drive: DriveState }) {
    const [starting, setStarting] = useState(false);
    const error = usePage().props.errors?.drive_folder as string | undefined;
    const ready = drive.configured && drive.hasFolder;

    if (isRunning(drive)) {
        return (
            <p className="flex items-center gap-2 font-body text-sm text-muted-foreground">
                <Icon name="clock" size={16} />
                Importando do Drive. As fotos aparecem aqui quando terminar.
            </p>
        );
    }

    if (!ready) {
        return (
            <Button asChild>
                <Link href={`/painel/fotos/${eventId}`}>
                    <Icon name="settings" size={16} /> Configurar a pasta do Drive
                </Link>
            </Button>
        );
    }

    return (
        <div className="flex flex-col items-center gap-3">
            <Button
                type="button"
                disabled={starting}
                onClick={() =>
                    router.post(
                        `/painel/fotos/${eventId}/importar-drive`,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setStarting(true),
                            onFinish: () => setStarting(false),
                        },
                    )
                }
            >
                <Icon name="download" size={16} />
                {starting ? 'Iniciando…' : 'Importar fotos do Drive'}
            </Button>
            {(error ?? drive.error) && (
                <p role="alert" className="max-w-80 font-body text-sm text-danger-text">
                    {error ?? drive.error}
                </p>
            )}
        </div>
    );
}

export default function PhotoPhotos({
    shell,
    drive,
    photos,
    filter,
    groups,
    noGroupCount,
    totalCount,
}: {
    shell: GalleryShellData;
    drive: DriveState;
    photos: Paginator<PhotoRow>;
    filter: string;
    groups: Group[];
    noGroupCount: number;
    totalCount: number;
}) {
    const base = `/painel/fotos/${shell.event.id}/galeria`;
    const running = isRunning(drive);

    // Enquanto a fila importa, recarrega a página (mesmo depois que as
    // primeiras fotos chegam): a lista cresce até a importação terminar.
    useEffect(() => {
        if (!running) {
            return;
        }

        const timer = setInterval(() => router.reload(), 3000);

        return () => clearInterval(timer);
    }, [running]);

    return (
        <>
            <Head title={`Painel — Fotos importadas de ${shell.event.title}`} />
            <PhotoGalleryShell shell={shell} tab="fotos">
                {running && totalCount > 0 && (
                    <p className="mb-5 flex items-center gap-2 rounded-card border border-border bg-surface px-4 py-3 font-body text-sm text-muted-foreground">
                        <Icon name="clock" size={16} className="shrink-0" />
                        Importando do Drive: {totalCount}{' '}
                        {totalCount === 1 ? 'foto até agora' : 'fotos até agora'}.
                    </p>
                )}
                {totalCount === 0 ? (
                    <div className="rounded-card border border-border py-14">
                        <EmptyState
                            icon="image"
                            title="Nenhuma foto importada"
                            description={
                                drive.configured && drive.hasFolder
                                    ? 'Traga as fotos da pasta do Drive deste evento, com turmas e rostos.'
                                    : 'Cole o link da pasta do Drive nos ajustes para importar as fotos deste evento.'
                            }
                            action={
                                <EmptyImport
                                    eventId={shell.event.id}
                                    drive={drive}
                                />
                            }
                        />
                    </div>
                ) : (
                    <>
                        <div
                            role="group"
                            aria-label="Filtrar por turma"
                            className="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
                        >
                            <Chip href={base} active={filter === ''}>
                                Todas
                                <span className="tabular-nums opacity-70">
                                    {totalCount}
                                </span>
                            </Chip>
                            {groups.map((g) => (
                                <Chip
                                    key={g.id}
                                    href={`${base}?turma=${g.id}`}
                                    active={filter === String(g.id)}
                                >
                                    {g.name}
                                    <span className="tabular-nums opacity-70">
                                        {g.count}
                                    </span>
                                </Chip>
                            ))}
                            {noGroupCount > 0 && (
                                <Chip
                                    href={`${base}?turma=sem`}
                                    active={filter === 'sem'}
                                >
                                    Sem turma
                                    <span className="tabular-nums opacity-70">
                                        {noGroupCount}
                                    </span>
                                </Chip>
                            )}
                        </div>

                        {photos.data.length === 0 ? (
                            <p className="py-10 text-center font-body text-sm text-muted-foreground">
                                Nenhuma foto nesta turma.
                            </p>
                        ) : (
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                                {photos.data.map((photo) => (
                                    <li
                                        key={photo.id}
                                        className="overflow-hidden rounded-card border border-border bg-surface"
                                    >
                                        <div className="aspect-[4/3] bg-surface-2">
                                            <img
                                                src={photo.thumb}
                                                alt={photo.filename}
                                                loading="lazy"
                                                className="size-full object-cover"
                                            />
                                        </div>
                                        <div className="space-y-1 px-2.5 py-2">
                                            <p
                                                className="truncate font-mono text-[11px] text-muted-foreground"
                                                title={photo.filename}
                                            >
                                                {photo.filename}
                                            </p>
                                            <p className="truncate font-body text-xs text-foreground">
                                                {photo.group ?? (
                                                    <span className="text-faint">
                                                        Sem turma
                                                    </span>
                                                )}
                                            </p>
                                            <p
                                                className="flex items-center gap-1 truncate font-body text-xs text-faint"
                                                title={photo.people.join(', ')}
                                            >
                                                <Icon name="users" size={12} />
                                                {photo.people.length === 0
                                                    ? 'Ninguém marcado'
                                                    : photo.people.join(', ')}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <Pagination links={photos.links} />
                    </>
                )}
            </PhotoGalleryShell>
        </>
    );
}
