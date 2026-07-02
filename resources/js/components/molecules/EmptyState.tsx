import type { ReactNode } from 'react';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon?: IconName;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

/**
 * Estado vazio com voz do domínio — "Nenhum ingresso ainda", nunca "No data yet".
 */
export function EmptyState({ icon = 'ticket', title, description, action, className }: EmptyStateProps) {
    return (
        <div className={cn('flex flex-col items-center text-center', className)}>
            <span className="mb-5 flex size-14 items-center justify-center rounded-card border border-border text-muted-foreground">
                <Icon name={icon} size={26} />
            </span>
            <h3 className="font-display text-display-sm uppercase text-foreground">{title}</h3>
            {description && (
                <p className="mt-2 max-w-60 font-body text-sm text-muted-foreground">{description}</p>
            )}
            {action && <div className="mt-6">{action}</div>}
        </div>
    );
}
