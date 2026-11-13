import { Head, Link } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import { BuyerAreaTabs } from '@/components/molecules/BuyerAreaTabs';
import { EmptyState } from '@/components/molecules/EmptyState';
import { photoCount } from '@/lib/photos/pricing';
import type { PhotoEvent } from '@/lib/photos/types';

interface OwnedPhoto {
    id: number;
    thumb: string;
    filename: string;
    width: number;
    height: number;
    /** Original já guardado: false nas fotos de demonstração ou ainda em preparação. */
    available: boolean;
    downloadUrl: string;
}

interface OwnedGallery {
    galleryId: number;
    event: PhotoEvent;
    galleryUrl: string;
    zipUrl: string;
    photos: OwnedPhoto[];
    paidAt: string | null;
}

interface Props {
    galleries: OwnedGallery[];
    needsPassword?: boolean;
}

export default function MyPhotosPage({ galleries, needsPassword = false }: Props) {
    return (
        <>
            <Head title="Minhas fotos" />
            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-12">
                <h1 className="font-display text-display-lg text-foreground uppercase">
                    Minhas fotos
                </h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    As fotos que você comprou, em alta e sem marca d'água.
                </p>

                <BuyerAreaTabs current="photos" />

                {needsPassword && galleries.length > 0 && (
                    <Link
                        href="/definir-senha"
                        className="mt-6 flex items-center justify-between gap-3 rounded-card border border-accent/40 bg-surface px-4 py-3 transition-colors hover:bg-surface-2"
                    >
                        <span className="flex items-center gap-2.5">
                            <Icon name="lock" size={18} className="text-accent" />
                            <span className="font-body text-sm text-foreground">
                                Crie uma senha para voltar às fotos sem depender do link por e-mail.
                            </span>
                        </span>
                        <Icon name="chevron-right" size={16} className="shrink-0 text-faint" />
                    </Link>
                )}

                {galleries.length === 0 ? (
                    <EmptyState
                        icon="image"
                        title="Nenhuma foto ainda"
                        description="Quando você comprar fotos de um espetáculo, elas aparecem aqui para baixar."
                        action={
                            <Link
                                href="/fotos"
                                className="inline-flex items-center gap-2 rounded-btn bg-accent px-5 py-3 font-body text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover"
                            >
                                Ver fotos dos espetáculos
                                <Icon name="arrow-right" size={16} />
                            </Link>
                        }
                        className="mt-14"
                    />
                ) : (
                    <div className="mt-8 flex flex-col gap-10">
                        {galleries.map((g) => (
                            <GallerySection key={g.galleryId} gallery={g} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function GallerySection({ gallery }: { gallery: OwnedGallery }) {
    const ready = gallery.photos.filter((p) => p.available).length;
    const pending = gallery.photos.length - ready;

    return (
        <section aria-labelledby={`g-${gallery.galleryId}`}>
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="min-w-0">
                    <p className="kicker text-faint">
                        {photoCount(gallery.photos.length)}
                        {gallery.paidAt ? ` · comprado em ${gallery.paidAt}` : ''}
                    </p>
                    <h2
                        id={`g-${gallery.galleryId}`}
                        className="mt-1 font-display text-display-sm text-foreground uppercase"
                    >
                        <Link href={gallery.galleryUrl} className="hover:underline">
                            {gallery.event.title}
                        </Link>
                    </h2>
                </div>
                {ready > 0 && (
                    <a
                        href={gallery.zipUrl}
                        className="inline-flex items-center gap-2 rounded-btn bg-accent px-4 py-2.5 font-body text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <Icon name="download" size={16} />
                        Baixar todas (.zip)
                    </a>
                )}
            </div>

            {pending > 0 && (
                <p
                    role="status"
                    className="mt-4 flex items-start gap-2 rounded-card border border-warning bg-warning/15 px-4 py-3 font-body text-sm text-foreground"
                >
                    <Icon name="info" size={16} className="mt-0.5 shrink-0 text-warning" />
                    {pending === 1
                        ? 'O original de 1 foto ainda está sendo preparado pelo fotógrafo.'
                        : `Os originais de ${pending} fotos ainda estão sendo preparados pelo fotógrafo.`}{' '}
                    Assim que chegarem, o download fica disponível aqui.
                </p>
            )}

            <ul className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                {gallery.photos.map((p) => (
                    <li
                        key={p.id}
                        className="overflow-hidden rounded-card border border-border bg-surface"
                    >
                        <div className="aspect-square bg-surface-2">
                            <img
                                src={p.thumb}
                                alt={p.filename}
                                loading="lazy"
                                className="size-full object-cover"
                            />
                        </div>
                        <div className="flex items-center justify-between gap-2 px-3 py-2.5">
                            <span className="truncate font-body text-xs text-muted-foreground">
                                {p.filename}
                            </span>
                            {p.available ? (
                                <a
                                    href={p.downloadUrl}
                                    aria-label={`Baixar ${p.filename}`}
                                    className="flex size-9 shrink-0 items-center justify-center rounded-full text-foreground transition-colors hover:bg-surface-2 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                                >
                                    <Icon name="download" size={18} />
                                </a>
                            ) : (
                                <span className="shrink-0 font-body text-[11px] text-faint">
                                    Em preparação
                                </span>
                            )}
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}
