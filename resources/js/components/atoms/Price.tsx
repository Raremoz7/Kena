import { cn } from '@/lib/utils';
import { formatBRL } from '@/lib/veludo/format';

interface PriceProps {
    value: number;
    className?: string;
    /** Mostra sinal (+/−) — útil para descontos e taxas. */
    signed?: boolean;
}

/**
 * Preço em Oswald com numerais tabulares (alinhamento de colunas).
 */
export function Price({ value, className, signed }: PriceProps) {
    const formatted = formatBRL(Math.abs(value));
    const sign = signed ? (value < 0 ? '−' : '+') : value < 0 ? '−' : '';

    return (
        <span className={cn('font-display tabular tracking-tight', className)}>
            {sign}
            {formatted}
        </span>
    );
}
