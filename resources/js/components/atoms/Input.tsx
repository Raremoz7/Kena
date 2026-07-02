import { forwardRef  } from 'react';
import type {InputHTMLAttributes} from 'react';
import { cn } from '@/lib/utils';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    invalid?: boolean;
}

/**
 * Input do Veludo. Foco em vinho com halo suave; estado de erro em danger.
 */
export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { className, invalid, ...props },
    ref,
) {
    return (
        <input
            ref={ref}
            aria-invalid={invalid || undefined}
            className={cn(
                'w-full rounded-input border bg-bg px-[14px] py-3 font-body text-sm text-foreground',
                'placeholder:text-faint transition-colors outline-none',
                'focus-visible:border-accent focus-visible:ring-[3px] focus-visible:ring-accent/20',
                'disabled:cursor-not-allowed disabled:opacity-50',
                invalid
                    ? 'border-danger focus-visible:border-danger focus-visible:ring-danger/20'
                    : 'border-border',
                className,
            )}
            {...props}
        />
    );
});
