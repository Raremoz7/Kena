import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Card — superfície com intenção. Não envolver tudo em card.
 */
export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('rounded-card border border-border bg-surface p-7', className)}
            {...props}
        />
    );
}
