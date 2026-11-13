import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Spinner } from '@/components/atoms/Spinner';
import { photoCount } from '@/lib/photos/pricing';
import type { PhotoQuote, PriceTier } from '@/lib/photos/pricing';
import type { GalleryPhoto } from '@/lib/photos/types';
import { cn } from '@/lib/utils';
import { formatBRL } from '@/lib/veludo/format';
import { PhotoSheet } from './PhotoSheet';

/** Frase que puxa para a próxima faixa, dizendo o que a pessoa ganha. */
export function nextTierHint(quote: PhotoQuote): string | null {
    const { next } = quote;

    if (!next) {
        return null;
    }

    const more = `mais ${photoCount(next.missing)}`;
    const each = formatBRL(next.unitPrice);

    if (quote.qty > 0 && next.total <= quote.total) {
        return `Com ${more}, o total cai para ${formatBRL(next.total)} (${each} cada)`;
    }

    return `Com ${more}, cada uma sai por ${each}`;
}

function TierProgress({
    quote,
    tiers,
}: {
    quote: PhotoQuote;
    tiers: PriceTier[];
}) {
    const hint = nextTierHint(quote);
    const target = quote.next?.minQty ?? quote.qty;
    const pct = target > 0 ? Math.min(100, (quote.qty / target) * 100) : 100;

    return (
        <div className="rounded-card border border-border bg-surface-2/60 p-4">
            <div className="flex items-center justify-between gap-3">
                <p className="font-body text-sm font-semibold text-foreground">
                    {quote.next ? 'Preço de pacote' : 'Preço de pacote ativado'}
                </p>
                <ul className="flex gap-1.5">
                    {tiers.map((t) => (
                        <li
                            key={t.minQty}
                            className={cn(
                                'rounded-full px-2 py-0.5 font-body text-[11px] whitespace-nowrap tabular-nums',
                                quote.unitPrice === t.unitPrice
                                    ? 'bg-accent font-semibold text-accent-fg'
                                    : 'bg-surface text-muted-foreground',
                            )}
                        >
                            {t.minQty === 1 ? 'avulsa' : `${t.minQty}+`}{' '}
                            {formatBRL(t.unitPrice)}
                        </li>
                    ))}
                </ul>
            </div>
            <div
                className="mt-3 h-1.5 overflow-hidden rounded-full bg-border"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={target}
                aria-valuenow={quote.qty}
                aria-label="Progresso até o preço de pacote"
            >
                <div
                    className="h-full rounded-full bg-accent transition-[width] duration-500"
                    style={{ width: `${pct}%` }}
                />
            </div>
            <p className="mt-2 font-body text-xs text-muted-foreground">
                {hint ??
                    `Todas as fotos saem por ${formatBRL(quote.unitPrice)}. Pode continuar escolhendo.`}
            </p>
        </div>
    );
}

interface CartBarProps {
    photos: GalleryPhoto[];
    quote: PhotoQuote;
    onOpen: () => void;
}

/** Barra fixa no rodapé enquanto há fotos escolhidas: total sempre à vista. */
export function PhotoCartBar({ photos, quote, onOpen }: CartBarProps) {
    const hint = nextTierHint(quote);
    const stack = photos.slice(-3).reverse();

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-0 z-40 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6 sm:pb-5">
            <div className="pointer-events-auto mx-auto flex max-w-2xl animate-in items-center gap-3 rounded-[16px] border border-border bg-surface/95 p-2.5 pl-3 shadow-[0_12px_40px_rgba(0,0,0,0.28)] backdrop-blur-md duration-300 fade-in slide-in-from-bottom-4">
                <div className="hidden shrink-0 -space-x-3 sm:flex">
                    {stack.map((p) => (
                        <img
                            key={p.id}
                            src={p.thumb}
                            alt=""
                            className="size-10 rounded-[8px] border-2 border-surface object-cover"
                        />
                    ))}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="flex items-baseline gap-2 font-body text-sm text-foreground">
                        <span className="font-semibold">
                            {photoCount(quote.qty)}
                        </span>
                        <Price value={quote.total} className="text-base" />
                        {quote.savings > 0 && (
                            <span className="truncate text-xs text-success-text">
                                economia de {formatBRL(quote.savings)}
                            </span>
                        )}
                    </p>
                    {hint && (
                        <p className="line-clamp-2 font-body text-xs leading-snug text-muted-foreground sm:truncate">
                            {hint}
                        </p>
                    )}
                </div>
                <button
                    type="button"
                    onClick={onOpen}
                    className="inline-flex shrink-0 items-center gap-2 rounded-btn bg-accent px-4 py-3 font-body text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface focus-visible:outline-none"
                >
                    <Icon name="bag" size={17} />
                    <span className="hidden sm:inline">Ver carrinho</span>
                    <span className="sm:hidden">Carrinho</span>
                </button>
            </div>
        </div>
    );
}

interface CartPanelProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    photos: GalleryPhoto[];
    quote: PhotoQuote;
    tiers: PriceTier[];
    onRemove: (id: number) => void;
    onClear: () => void;
    onCheckout: () => void;
    /** Pedido sendo criado: trava o botão para não abrir dois pedidos. */
    submitting?: boolean;
}

export function PhotoCartPanel({
    open,
    onOpenChange,
    photos,
    quote,
    tiers,
    onRemove,
    onClear,
    onCheckout,
    submitting = false,
}: CartPanelProps) {
    return (
        <PhotoSheet
            open={open}
            onOpenChange={onOpenChange}
            variant="side"
            title="Seu carrinho"
            description={
                photos.length > 0
                    ? photoCount(photos.length)
                    : 'Nenhuma foto escolhida'
            }
            footer={
                photos.length > 0 && (
                    <>
                        <dl className="space-y-1.5 font-body text-sm">
                            <div className="flex justify-between text-muted-foreground">
                                <dt>
                                    {photoCount(quote.qty)} ×{' '}
                                    {formatBRL(quote.unitPrice)}
                                </dt>
                                <dd className="tabular-nums">
                                    {formatBRL(quote.total)}
                                </dd>
                            </div>
                            {quote.savings > 0 && (
                                <div className="flex justify-between text-success-text">
                                    <dt>Economia do pacote</dt>
                                    <dd className="tabular-nums">
                                        −{formatBRL(quote.savings)}
                                    </dd>
                                </div>
                            )}
                            <div className="flex items-baseline justify-between border-t border-border pt-2.5">
                                <dt className="font-semibold text-foreground">
                                    Total
                                </dt>
                                <dd>
                                    <Price
                                        value={quote.total}
                                        className="text-2xl text-foreground"
                                    />
                                </dd>
                            </div>
                        </dl>
                        <button
                            type="button"
                            onClick={onCheckout}
                            disabled={submitting}
                            aria-busy={submitting}
                            className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-btn bg-accent px-5 py-4 font-body text-base font-semibold text-accent-fg transition-colors hover:bg-accent-hover focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface focus-visible:outline-none disabled:opacity-60"
                        >
                            {submitting ? (
                                <Spinner />
                            ) : (
                                <>
                                    Finalizar compra
                                    <Icon name="arrow-right" size={18} />
                                </>
                            )}
                        </button>
                        <p className="mt-2.5 flex items-center justify-center gap-1.5 text-center font-body text-xs text-faint">
                            <Icon name="lock" size={13} />
                            Fotos em alta, sem marca d'água, liberadas logo após
                            o pagamento
                        </p>
                    </>
                )
            }
        >
            {photos.length === 0 ? (
                <div className="flex flex-col items-center py-12 text-center">
                    <span className="flex size-14 items-center justify-center rounded-full bg-surface-2 text-muted-foreground">
                        <Icon name="bag" size={24} />
                    </span>
                    <p className="mt-4 font-body text-sm font-semibold text-foreground">
                        Nenhuma foto escolhida
                    </p>
                    <p className="mt-1 max-w-[16rem] font-body text-sm text-muted-foreground">
                        Toque no + de uma foto para colocá-la aqui.
                    </p>
                </div>
            ) : (
                <>
                    <TierProgress quote={quote} tiers={tiers} />
                    <div className="mt-4 flex items-center justify-between">
                        <p className="font-body text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Escolhidas
                        </p>
                        <button
                            type="button"
                            onClick={onClear}
                            className="font-body text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            Esvaziar
                        </button>
                    </div>
                    <ul className="mt-2 grid grid-cols-3 gap-2">
                        {photos.map((p) => (
                            <li
                                key={p.id}
                                className="group relative aspect-square overflow-hidden rounded-[8px] bg-surface-2"
                            >
                                <img
                                    src={p.thumb}
                                    alt=""
                                    className="size-full object-cover"
                                />
                                <button
                                    type="button"
                                    onClick={() => onRemove(p.id)}
                                    aria-label="Tirar do carrinho"
                                    className="absolute top-1.5 right-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-sm transition-colors hover:bg-danger focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                >
                                    <Icon
                                        name="close"
                                        size={14}
                                        strokeWidth={2.2}
                                    />
                                </button>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </PhotoSheet>
    );
}
