import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { cn } from '@/lib/utils';

export interface GalleryShellData {
    event: { id: number; title: string; slug: string };
    exists: boolean;
    status: 'draft' | 'published' | null;
    photoCount: number;
    peopleCount: number;
}

type Tab = 'ajustes' | 'pessoas' | 'fotos';

/**
 * Moldura das telas da galeria de um evento: título, status, publicar/despublicar
 * (com confirmação) e as abas Ajustes, Pessoas e Fotos.
 */
export function PhotoGalleryShell({
    shell,
    tab,
    width = 'full',
    children,
}: {
    shell: GalleryShellData;
    tab: Tab;
    width?: 'full' | 'medium';
    children: ReactNode;
}) {
    const [confirming, setConfirming] = useState<'publish' | 'unpublish' | null>(
        null,
    );
    const [busy, setBusy] = useState(false);
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const base = `/painel/fotos/${shell.event.id}`;
    const published = shell.status === 'published';

    const tabs: { key: Tab; label: string; href: string; count?: number }[] = [
        { key: 'ajustes', label: 'Ajustes', href: base },
        {
            key: 'pessoas',
            label: 'Pessoas',
            href: `${base}/pessoas`,
            count: shell.peopleCount,
        },
        {
            key: 'fotos',
            label: 'Fotos',
            href: `${base}/galeria`,
            count: shell.photoCount,
        },
    ];

    function run(kind: 'publish' | 'unpublish') {
        setBusy(true);
        router.post(
            `${base}/${kind === 'publish' ? 'publicar' : 'despublicar'}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    setConfirming(null);
                },
            },
        );
    }

    return (
        <>
            <AdminPage width={width}>
                <PageHeader
                    back={{ href: '/painel/fotos', label: 'Fotos' }}
                    title={shell.event.title}
                    description={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            {shell.exists ? (
                                <Badge tone={published ? 'success' : 'neutral'}>
                                    {published ? 'Publicada' : 'Rascunho'}
                                </Badge>
                            ) : (
                                <Badge tone="neutral">Sem galeria</Badge>
                            )}
                            {published && (
                                <a
                                    href={`/fotos/${shell.event.slug}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-accent-text hover:underline"
                                >
                                    Ver no site
                                    <Icon name="arrow-right" size={14} />
                                </a>
                            )}
                        </span>
                    }
                    actions={
                        shell.exists ? (
                            published ? (
                                <Button
                                    variant="secondary"
                                    onClick={() => setConfirming('unpublish')}
                                    className="w-full sm:w-auto"
                                >
                                    Despublicar
                                </Button>
                            ) : (
                                <Button
                                    onClick={() => setConfirming('publish')}
                                    className="w-full sm:w-auto"
                                >
                                    Publicar galeria
                                </Button>
                            )
                        ) : undefined
                    }
                />

                {errors?.publish && (
                    <p
                        role="alert"
                        className="mb-4 flex items-start gap-2 rounded-card border border-warning bg-warning/10 px-4 py-3 font-body text-sm text-foreground"
                    >
                        <Icon
                            name="alert"
                            size={18}
                            className="mt-0.5 shrink-0"
                        />
                        {errors.publish}
                    </p>
                )}

                {shell.exists && (
                    <nav
                        aria-label="Seções da galeria"
                        className="mb-6 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-border"
                    >
                        {tabs.map((t) => (
                            <Link
                                key={t.key}
                                href={t.href}
                                aria-current={t.key === tab ? 'page' : undefined}
                                className={cn(
                                    'inline-flex min-h-11 items-center gap-2 border-b-2 px-3 font-body text-sm whitespace-nowrap transition-colors',
                                    t.key === tab
                                        ? 'border-accent font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t.label}
                                {t.count !== undefined && (
                                    <span className="rounded-badge bg-surface-2 px-1.5 py-0.5 text-[11px] text-muted-foreground tabular-nums">
                                        {t.count}
                                    </span>
                                )}
                            </Link>
                        ))}
                    </nav>
                )}

                {children}
            </AdminPage>

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && !busy && setConfirming(null)}
                tone={confirming === 'publish' ? 'default' : 'danger'}
                title={
                    confirming === 'publish'
                        ? 'Publicar galeria?'
                        : 'Despublicar galeria?'
                }
                description={
                    confirming === 'publish'
                        ? `As ${shell.photoCount} fotos ficam à venda em /fotos para qualquer visitante.`
                        : 'A galeria some de /fotos e ninguém consegue abrir o link até você publicar de novo. As fotos continuam guardadas.'
                }
                confirmLabel={
                    confirming === 'publish' ? 'Publicar' : 'Despublicar'
                }
                onConfirm={() => confirming && run(confirming)}
            />
        </>
    );
}
