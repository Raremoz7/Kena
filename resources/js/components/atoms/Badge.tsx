import { cva  } from 'class-variance-authority';
import type {VariantProps} from 'class-variance-authority';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Badge de status — fundo SÓLIDO na cor semântica (nunca tintado/translúcido).
 */
const badge = cva(
    'inline-flex items-center gap-1 rounded-badge px-[9px] py-1 font-body text-[11px] font-semibold leading-none',
    {
        variants: {
            tone: {
                success: 'bg-success text-white',
                warning: 'bg-warning text-warning-fg',
                danger: 'bg-danger text-white',
                accent: 'bg-accent text-accent-fg',
                info: 'bg-info text-white',
                neutral: 'bg-neutral-fill text-white',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badge> {}

export function Badge({ className, tone, ...props }: BadgeProps) {
    return <span className={cn(badge({ tone }), className)} {...props} />;
}
