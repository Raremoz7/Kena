import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { cn } from '@/lib/utils';
import type { Sector } from '@/lib/veludo/types';

interface SessionListProps {
    eventSlug: string;
    sessionId: number;
    sessionLabel: string;
    sectors: Sector[];
}

/**
 * Painel de compra: sessão + setores com preço. Setor esgotado fica esmaecido.
 */
export function SessionList({ sessionLabel, sectors }: SessionListProps) {
    return (
        <div className="rounded-card border border-border bg-surface p-6">
            <div className="flex items-center gap-2 border-b border-border pb-4">
                <Icon name="calendar" size={16} className="text-accent" />
                <span className="font-body text-sm font-medium text-foreground">{sessionLabel}</span>
            </div>

            <p className="kicker mt-5 mb-3 text-faint">Setores</p>
            <ul className="flex flex-col">
                {sectors.map((sector) => (
                    <li
                        key={sector.id}
                        className={cn(
                            'flex items-center justify-between gap-4 border-b border-border py-3 last:border-b-0',
                            sector.soldOut && 'opacity-50',
                        )}
                    >
                        <div className="min-w-0">
                            <p className="font-body text-sm font-medium text-foreground">{sector.name}</p>
                            <p className="font-body text-xs text-faint">
                                {sector.soldOut
                                    ? 'Esgotado'
                                    : `${sector.availableCount} lugares disponíveis`}
                            </p>
                        </div>
                        <Price value={sector.price} className="text-lg text-foreground" />
                    </li>
                ))}
            </ul>

            <Button asChild block className="mt-6">
                <a href="#assentos">
                    Escolher assentos
                    <Icon name="chevron-right" size={18} className="rotate-90" />
                </a>
            </Button>
        </div>
    );
}
