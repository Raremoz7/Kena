import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Tag de categoria — apenas kicker (uppercase tracked) em vinho mais claro.
 * SEM caixa, SEM fundo.
 */
export function Tag({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
    return <span className={cn('kicker text-tag-wine', className)} {...props} />;
}
