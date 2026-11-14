import { Head, Link } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Tag } from '@/components/atoms/Tag';
import { EmptyState } from '@/components/molecules/EmptyState';
import { photoCount } from '@/lib/photos/pricing';
import type { GalleryListItem } from '@/lib/photos/types';

/** Mosaico de capa: uma foto grande e duas menores, como uma prévia da galeria. */
function CoverMosaic({
    covers,
    fallback,
}: {
    covers: string[];
    fallback?: string;
}) {
    if (covers.length < 3) {
        return (
            <div className="h-48 bg-surface-2">
                {(covers[0] ?? fallback) && (
                    <img
                        src={covers[0] ?? fallback}
                        alt=""
                        className="size-full object-cover"
                    />
                )}
            </div>
        );
    }

    return (
        <div className="grid h-48 grid-cols-3 grid-rows-2 gap-0.5 bg-border">
            <img
                src={covers[0]}
                alt=""
                className="col-span-2 row-span-2 size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
            />
            <img src={covers[1]} alt="" className="size-full object-cover" />
            <img src={covers[2]} alt="" className="size-full object-cover" />
        </div>
    );
}

function GalleryCard({ gallery }: { gallery: GalleryListItem }) {
    const { event } = gallery;
    const lowest = Math.min(...gallery.tiers.map((t) => t.unitPrice));

    return (
        <Link
            href={`/fotos/${event.slug}`}
            className="group block overflow-hidden rounded-card border border-border bg-surface transition-colors hover:border-accent/50"
        >
            <div className="relative overflow-hidden">
                <CoverMosaic
                    covers={gallery.covers}
                    fallback={event.bannerImage}
                />
                <span className="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-black/65 px-2.5 py-1 font-body text-xs font-semibold text-white backdrop-blur-sm">
                    <Icon name={gallery.locked ? 'lock' : 'image'} size={14} />
                    {gallery.locked
                        ? 'Com código ou ingresso'
                        : photoCount(gallery.photoCount)}
                </span>
            </div>
            <div className="p-5">
                <Tag>{event.kicker}</Tag>
                <h3 className="mt-1.5 font-display text-display-sm text-foreground uppercase">
                    {event.title}
                </h3>
                <p className="mt-2 flex items-center gap-1.5 font-body text-sm text-muted-foreground">
                    <Icon name="calendar" size={15} />
                    {event.dateLabel}
                </p>
                <p className="flex items-center gap-1.5 font-body text-sm text-muted-foreground">
                    <Icon name="image" size={15} />
                    {photoCount(gallery.photoCount)}
                </p>
                <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                    <span className="font-body text-xs text-faint">
                        fotos a partir de
                    </span>
                    <span className="flex items-center gap-2">
                        <Price
                            value={lowest}
                            className="text-lg text-foreground"
                        />
                        <Icon
                            name={gallery.locked ? 'lock' : 'arrow-right'}
                            size={16}
                            className="text-accent-text transition-transform group-hover:translate-x-0.5"
                        />
                    </span>
                </div>
            </div>
        </Link>
    );
}

export default function PhotosPage({
    galleries,
}: {
    galleries: GalleryListItem[];
}) {
    return (
        <>
            <Head title="Fotos" />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
                <Tag>Fotos do espetáculo</Tag>
                <h1 className="mt-2 font-display text-display-lg text-foreground uppercase">
                    Fotos
                </h1>
                <p className="mt-1 max-w-lg font-body text-sm text-muted-foreground">
                    Escolha o evento, encontre quem você foi ver pelo rosto ou
                    pela turma e leve as fotos em alta, sem marca d'água.
                </p>

                {galleries.length === 0 ? (
                    <EmptyState
                        icon="image"
                        title="Nenhuma galeria publicada ainda"
                        description="As fotos aparecem aqui alguns dias depois do espetáculo."
                        className="mt-10"
                    />
                ) : (
                    <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {galleries.map((g) => (
                            <GalleryCard key={g.event.id} gallery={g} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
