import { Slot } from '@radix-ui/react-slot';
import { cva  } from 'class-variance-authority';
import type {VariantProps} from 'class-variance-authority';
import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Button do Veludo. Raio varia por função (pill nunca; primary/secondary = btn 8px,
 * size sm = badge 6px). Cor sempre por token semântico.
 */
const button = cva(
    'inline-flex items-center justify-center gap-2 font-body font-semibold whitespace-nowrap ' +
        'transition-colors duration-150 outline-none focus-visible:ring-2 focus-visible:ring-accent ' +
        'focus-visible:ring-offset-2 focus-visible:ring-offset-bg disabled:pointer-events-none ' +
        "disabled:bg-surface-2 disabled:text-faint disabled:border-transparent [&_svg]:shrink-0",
    {
        variants: {
            variant: {
                primary: 'bg-accent text-accent-fg hover:bg-accent-hover',
                secondary: 'border border-border-strong text-foreground hover:bg-surface-2',
                ghost: 'text-accent-text hover:bg-surface-2',
                success: 'bg-success text-white hover:brightness-110',
                danger: 'border border-danger text-danger-text hover:bg-danger/10',
                'outline-danger': 'border border-danger text-danger-text hover:bg-danger/10',
            },
            size: {
                md: 'px-[22px] py-[14px] text-sm rounded-btn',
                sm: 'px-4 py-[9px] text-xs rounded-badge',
                lg: 'px-7 py-4 text-base rounded-btn',
                icon: 'size-10 rounded-btn',
            },
            block: { true: 'w-full' },
        },
        defaultVariants: { variant: 'primary', size: 'md' },
    },
);

export interface ButtonProps
    extends ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof button> {
    asChild?: boolean;
}

export function Button({ className, variant, size, block, asChild, ...props }: ButtonProps) {
    const Comp = asChild ? Slot : 'button';

    return <Comp className={cn(button({ variant, size, block }), className)} {...props} />;
}

export { button as buttonVariants };
