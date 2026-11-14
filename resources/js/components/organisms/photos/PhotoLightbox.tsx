import * as Dialog from '@radix-ui/react-dialog';
import { useEffect, useRef } from 'react';
import type { PointerEvent as ReactPointerEvent } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { firstName } from '@/lib/photos/types';
import type { GalleryPerson, GalleryPhoto } from '@/lib/photos/types';
import { cn } from '@/lib/utils';

interface PhotoLightboxProps {
    photos: GalleryPhoto[];
    index: number | null;
    onIndexChange: (index: number | null) => void;
    peopleById: Map<number, GalleryPerson>;
    isInCart: (id: number) => boolean;
    onToggleCart: (id: number) => void;
    /** Toca no nome de quem está na foto para filtrar só por ela. */
    onFilterPerson: (id: number) => void;
    unitPriceLabel: string;
}

/**
 * Foto em tela cheia: setas e teclado no desktop, arrastar para o lado no
 * celular. O botão de carrinho fica no alcance do polegar.
 */
export function PhotoLightbox({
    photos,
    index,
    onIndexChange,
    peopleById,
    isInCart,
    onToggleCart,
    onFilterPerson,
    unitPriceLabel,
}: PhotoLightboxProps) {
    const open = index !== null && photos[index] !== undefined;
    const photo = open ? photos[index] : null;
    const startX = useRef<number | null>(null);

    const go = (delta: number) => {
        if (index === null) {
            return;
        }

        const next = index + delta;

        if (next >= 0 && next < photos.length) {
            onIndexChange(next);
        }
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight') {
                go(1);
            } else if (e.key === 'ArrowLeft') {
                go(-1);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    // Pré-carrega as vizinhas: a troca fica instantânea.
    useEffect(() => {
        if (index === null) {
            return;
        }

        [photos[index - 1], photos[index + 1]].forEach((p) => {
            if (p) {
                new Image().src = p.preview;
            }
        });
    }, [index, photos]);

    function onPointerDown(e: ReactPointerEvent) {
        startX.current = e.clientX;
    }

    function onPointerUp(e: ReactPointerEvent) {
        if (startX.current === null) {
            return;
        }

        const dx = e.clientX - startX.current;
        startX.current = null;

        if (Math.abs(dx) > 50) {
            go(dx < 0 ? 1 : -1);
        }
    }

    const inCart = photo ? isInCart(photo.id) : false;
    const tagged = photo
        ? photo.people
              .map((id) => peopleById.get(id))
              .filter((p): p is GalleryPerson => !!p)
        : [];

    return (
        <Dialog.Root
            open={open}
            onOpenChange={(o) => !o && onIndexChange(null)}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[80] bg-[rgb(11_8_9)] data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content
                    aria-describedby={undefined}
                    className="fixed inset-0 z-[80] flex flex-col text-white outline-none data-[state=open]:animate-in data-[state=open]:fade-in"
                >
                    <Dialog.Title className="sr-only">
                        Foto {index !== null ? index + 1 : ''} de{' '}
                        {photos.length}
                    </Dialog.Title>

                    <div className="flex items-center justify-between px-4 pt-[max(0.75rem,env(safe-area-inset-top))] pb-2 sm:px-6">
                        <span className="font-body text-sm text-white/70 tabular-nums">
                            {index !== null ? index + 1 : 0}{' '}
                            <span className="text-white/40">
                                de {photos.length}
                            </span>
                        </span>
                        <Dialog.Close
                            aria-label="Fechar"
                            className="inline-flex size-10 items-center justify-center rounded-full text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                        >
                            <Icon name="close" size={20} />
                        </Dialog.Close>
                    </div>

                    <div
                        className="relative flex min-h-0 flex-1 touch-pan-y items-center justify-center px-2 select-none sm:px-20"
                        onPointerDown={onPointerDown}
                        onPointerUp={onPointerUp}
                    >
                        {photo && (
                            <img
                                key={photo.id}
                                src={photo.preview}
                                alt=""
                                draggable={false}
                                onContextMenu={(e) => e.preventDefault()}
                                className="max-h-full max-w-full animate-in rounded-[4px] object-contain duration-200 fade-in"
                            />
                        )}
                        {[-1, 1].map((d) => (
                            <button
                                key={d}
                                type="button"
                                onClick={() => go(d)}
                                disabled={
                                    index === null ||
                                    index + d < 0 ||
                                    index + d >= photos.length
                                }
                                aria-label={
                                    d < 0 ? 'Foto anterior' : 'Próxima foto'
                                }
                                className={cn(
                                    'absolute top-1/2 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition-all hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none disabled:opacity-0 sm:flex',
                                    d < 0 ? 'left-4' : 'right-4',
                                )}
                            >
                                <Icon
                                    name={
                                        d < 0 ? 'chevron-left' : 'chevron-right'
                                    }
                                    size={22}
                                />
                            </button>
                        ))}
                    </div>

                    <div className="mx-auto flex w-full max-w-3xl flex-col gap-3 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div className="flex min-h-9 flex-wrap items-center gap-1.5">
                            {tagged.length > 0 ? (
                                <>
                                    <span className="mr-0.5 font-body text-xs text-white/50">
                                        Nesta foto
                                    </span>
                                    {tagged.map((p) => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            onClick={() => onFilterPerson(p.id)}
                                            title={`Ver só as fotos de ${p.name}`}
                                            className="inline-flex items-center gap-1.5 rounded-full bg-white/10 py-1 pr-3 pl-1 font-body text-xs text-white transition-colors hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                        >
                                            {p.face && (
                                                <img
                                                    src={p.face}
                                                    alt=""
                                                    className="size-6 rounded-full object-cover"
                                                />
                                            )}
                                            {firstName(p.name)}
                                        </button>
                                    ))}
                                </>
                            ) : (
                                <span className="font-body text-xs text-white/50">
                                    Ninguém marcado nesta foto
                                </span>
                            )}
                        </div>
                        {photo && (
                            <button
                                type="button"
                                onClick={() => onToggleCart(photo.id)}
                                aria-pressed={inCart}
                                className={cn(
                                    'inline-flex shrink-0 items-center justify-center gap-2 rounded-btn px-5 py-3.5 font-body text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none',
                                    inCart
                                        ? 'bg-white/10 text-white hover:bg-white/15'
                                        : 'bg-accent text-accent-fg hover:bg-accent-hover',
                                )}
                            >
                                <Icon
                                    name={inCart ? 'check' : 'bag'}
                                    size={18}
                                />
                                {inCart
                                    ? 'No carrinho · tirar'
                                    : `Pôr no carrinho · ${unitPriceLabel}`}
                            </button>
                        )}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
