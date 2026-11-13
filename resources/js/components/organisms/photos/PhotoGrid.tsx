import type { CSSProperties } from 'react';
import { Icon } from '@/components/atoms/Icon';
import type { GalleryPhoto } from '@/lib/photos/types';
import { cn } from '@/lib/utils';

interface PhotoGridProps {
    photos: GalleryPhoto[];
    isInCart: (id: number) => boolean;
    onOpen: (index: number) => void;
    onToggleCart: (id: number) => void;
}

/**
 * Grade justificada (linhas de mesma altura, cada foto na sua proporção), como
 * no Google Fotos: nada é cortado, vertical e horizontal convivem sem buraco.
 * A altura da linha vem de `--row`; a largura de cada item é proporcional.
 */
export function PhotoGrid({
    photos,
    isInCart,
    onOpen,
    onToggleCart,
}: PhotoGridProps) {
    return (
        <ul className="flex flex-wrap gap-1.5 [--row:6.75rem] sm:gap-2 sm:[--row:12rem] lg:[--row:14rem]">
            {photos.map((photo, index) => {
                const ratio = photo.width / photo.height;
                const inCart = isInCart(photo.id);

                return (
                    <li
                        key={photo.id}
                        className="relative"
                        style={
                            {
                                flexGrow: ratio * 100,
                                width: `calc(var(--row) * ${ratio})`,
                            } as CSSProperties
                        }
                    >
                        <div
                            className={cn(
                                'group relative overflow-hidden rounded-[6px] transition-colors duration-200',
                                // Escolhida: a foto recua dentro de uma moldura na cor de destaque.
                                inCart ? 'bg-accent/20' : 'bg-surface-2',
                            )}
                            style={{ paddingBottom: `${100 / ratio}%` }}
                        >
                            <button
                                type="button"
                                onClick={() => onOpen(index)}
                                className="absolute inset-0 outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-inset"
                                aria-label={`Ver foto ${index + 1}`}
                            >
                                <img
                                    src={photo.thumb}
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    draggable={false}
                                    className={cn(
                                        'size-full object-cover transition-all duration-300',
                                        inCart
                                            ? 'scale-[0.88] rounded-[4px]'
                                            : 'group-hover:scale-[1.03]',
                                    )}
                                />
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-0 bg-gradient-to-b from-black/35 via-transparent to-transparent opacity-0 transition-opacity group-hover:opacity-100"
                                />
                            </button>
                            <button
                                type="button"
                                onClick={() => onToggleCart(photo.id)}
                                aria-pressed={inCart}
                                aria-label={
                                    inCart
                                        ? 'Tirar do carrinho'
                                        : 'Pôr no carrinho'
                                }
                                className={cn(
                                    'absolute top-2 right-2 flex size-9 items-center justify-center rounded-full shadow-md transition-all duration-200 outline-none focus-visible:ring-2 focus-visible:ring-white',
                                    inCart
                                        ? 'scale-100 bg-accent text-accent-fg opacity-100'
                                        : 'bg-black/55 text-white backdrop-blur-sm hover:bg-black/75 sm:scale-90 sm:opacity-0 sm:group-hover:scale-100 sm:group-hover:opacity-100 sm:focus-visible:opacity-100',
                                )}
                            >
                                <Icon
                                    name={inCart ? 'check' : 'plus'}
                                    size={18}
                                    strokeWidth={inCart ? 2.6 : 2}
                                />
                            </button>
                        </div>
                    </li>
                );
            })}
            {/* Preenche a última linha para as fotos dela não esticarem. */}
            <li aria-hidden="true" className="grow-[10000]" />
        </ul>
    );
}
