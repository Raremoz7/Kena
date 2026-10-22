import { Badge } from '@/components/atoms/Badge';
import { Price } from '@/components/atoms/Price';

export interface LotInfo {
    current: { name: string; price: number; remaining: number | null } | null;
    next: { name: string; price: number } | null;
    soldOut: boolean;
}

/** Lote vendendo agora (e o próximo), quando a sessão vende por lotes. */
export function LotBanner({
    lot,
    className,
}: {
    lot: LotInfo | null;
    className?: string;
}) {
    if (!lot) {
        return null;
    }

    if (lot.soldOut || !lot.current) {
        return (
            <p
                className={`font-body text-sm text-muted-foreground ${className ?? ''}`}
            >
                <Badge tone="danger">Esgotado</Badge> Todos os lotes desta
                sessão foram vendidos.
            </p>
        );
    }

    return (
        <div
            className={`flex flex-wrap items-center gap-x-3 gap-y-1 font-body text-sm ${className ?? ''}`}
        >
            <Badge tone="success">{lot.current.name}</Badge>
            <Price value={lot.current.price} className="text-foreground" />
            {lot.current.remaining !== null && lot.current.remaining <= 20 && (
                <span className="text-warning-text">
                    Últimos {lot.current.remaining}{' '}
                    {lot.current.remaining === 1 ? 'ingresso' : 'ingressos'}{' '}
                    deste lote
                </span>
            )}
            {lot.next && (
                <span className="text-faint">
                    Depois: {lot.next.name} a{' '}
                    <Price
                        value={lot.next.price}
                        className="inline text-faint"
                    />
                </span>
            )}
        </div>
    );
}
