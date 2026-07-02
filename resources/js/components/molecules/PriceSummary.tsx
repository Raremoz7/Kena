import { Price } from '@/components/atoms/Price';
import { cn } from '@/lib/utils';
import type { PriceLine } from '@/lib/veludo/types';

interface PriceSummaryProps {
    lines: PriceLine[];
    total: number;
    className?: string;
}

/**
 * Resumo de preço: itens, descontos e taxas, com total destacado em Oswald.
 */
export function PriceSummary({ lines, total, className }: PriceSummaryProps) {
    return (
        <div className={cn('flex flex-col gap-3', className)}>
            {lines.map((line, i) => (
                <div key={i} className="flex items-baseline justify-between gap-4">
                    <span
                        className={cn(
                            'font-body text-sm',
                            line.tone === 'success'
                                ? 'text-success'
                                : line.tone === 'muted'
                                  ? 'text-faint'
                                  : 'text-muted-foreground',
                        )}
                    >
                        {line.label}
                    </span>
                    <Price
                        value={line.value}
                        signed={line.value < 0}
                        className={cn(
                            'text-sm',
                            line.tone === 'success' ? 'text-success' : 'text-foreground',
                        )}
                    />
                </div>
            ))}

            <div className="mt-1 flex items-baseline justify-between gap-4 border-t border-border pt-4">
                <span className="font-body text-sm font-semibold text-foreground">Total</span>
                <Price value={total} className="text-display-md text-foreground" />
            </div>
        </div>
    );
}
