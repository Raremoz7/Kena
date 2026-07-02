import type { ReactNode } from 'react';
import { Badge  } from '@/components/atoms/Badge';
import type {BadgeProps} from '@/components/atoms/Badge';
import { QrCode } from '@/components/atoms/QrCode';
import { cn } from '@/lib/utils';
import type { TicketInfo, TicketStatus } from '@/lib/veludo/types';

const stripe: Record<TicketStatus, string> = {
    valid: 'bg-accent',
    used: 'bg-[oklch(0.4_0.012_50)]',
    transferred: 'bg-info',
    cancelled: 'bg-danger',
    refunded: 'bg-danger',
};

const tone: Record<TicketStatus, NonNullable<BadgeProps['tone']>> = {
    valid: 'accent',
    used: 'neutral',
    transferred: 'info',
    cancelled: 'danger',
    refunded: 'danger',
};

interface TicketStubProps {
    ticket: TicketInfo;
    actions?: ReactNode;
    showQr?: boolean;
    className?: string;
}

/**
 * Ingresso visual — faixa de status no topo, dados à esquerda, QR à direita
 * separado por picote tracejado.
 */
export function TicketStub({ ticket, actions, showQr = true, className }: TicketStubProps) {
    return (
        <article
            className={cn(
                'overflow-hidden rounded-card border border-border bg-surface',
                className,
            )}
        >
            <div className={cn('h-[5px] w-full', stripe[ticket.status])} />
            <div className="flex items-stretch gap-5 p-5">
                <div className="flex min-w-0 flex-1 flex-col">
                    <div className="flex items-center justify-between gap-3">
                        <span className="kicker text-[oklch(0.72_0.13_24)]">{ticket.kicker}</span>
                        <Badge tone={tone[ticket.status]}>{ticket.statusLabel}</Badge>
                    </div>
                    <div className="mt-2 flex items-baseline gap-2">
                        <span className="font-display text-display-md uppercase text-foreground">
                            {ticket.seatLabel}
                        </span>
                        <span className="font-body text-sm text-muted-foreground">
                            · {ticket.sectorName}
                        </span>
                    </div>
                    <p className="mt-1 truncate font-body text-sm text-foreground">{ticket.eventTitle}</p>
                    <p className="font-body text-xs text-muted-foreground">
                        {ticket.dateLabel} · {ticket.venueName}
                    </p>
                    <p className="mt-auto pt-3 font-mono text-[11px] text-faint">{ticket.code}</p>
                    {actions && <div className="mt-3 flex gap-2">{actions}</div>}
                </div>

                {showQr && (
                    <div className="flex items-center border-l border-dashed border-border pl-5">
                        <QrCode value={ticket.code} size={72} />
                    </div>
                )}
            </div>
        </article>
    );
}
