import { Price } from '@/components/atoms/Price';
import type { Seat } from '@/lib/veludo/types';

function Row({ k, v }: { k: string; v: string }) {
    return (
        <div className="flex items-center justify-between gap-6">
            <dt className="font-body text-xs text-faint">{k}</dt>
            <dd className="font-body text-xs font-medium text-foreground/85">{v}</dd>
        </div>
    );
}

/**
 * Conteúdo do tooltip de assento (renderizado dentro do Tooltip do SeatMap).
 */
export function SeatTooltipContent({ seat }: { seat: Seat }) {
    return (
        <div className="w-[212px]">
            <div className="mb-3 flex items-baseline justify-between gap-3">
                <span className="font-display text-display-sm uppercase text-foreground">
                    Fila {seat.row} · {seat.number}
                </span>
                <Price value={seat.price} className="text-base text-foreground" />
            </div>
            <dl className="flex flex-col gap-2">
                <Row k="Setor" v={seat.sectorName} />
                <Row
                    k="Tipo"
                    v={
                        seat.kind === 'accessible'
                            ? 'Cadeirante'
                            : seat.kind === 'companion'
                              ? 'Acompanhante'
                              : 'Inteira'
                    }
                />
                <Row k="Visibilidade" v={seat.visibility ?? '—'} />
            </dl>
        </div>
    );
}
