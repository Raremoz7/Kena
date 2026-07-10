import { forwardRef, useContext  } from 'react';
import type {InputHTMLAttributes} from 'react';
import { FieldContext } from '@/components/atoms/field-context';
import { cn } from '@/lib/utils';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    invalid?: boolean;
}

/**
 * Input do Veludo. Foco em vinho com halo suave; estado de erro em danger.
 * Herda o estado de erro do FormField via FieldContext quando `invalid` não é
 * passado explicitamente.
 */
export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { className, invalid, 'aria-describedby': describedBy, ...props },
    ref,
) {
    const field = useContext(FieldContext);
    const isInvalid = invalid ?? field.invalid;

    return (
        <input
            ref={ref}
            aria-invalid={isInvalid || undefined}
            aria-describedby={describedBy ?? (isInvalid ? field.errorId : undefined)}
            className={cn(
                'w-full rounded-input border bg-bg px-[14px] py-3 font-body text-sm text-foreground',
                'placeholder:text-faint transition-colors outline-none',
                'focus-visible:border-accent focus-visible:ring-[3px] focus-visible:ring-accent/20',
                'disabled:cursor-not-allowed disabled:opacity-50',
                isInvalid
                    ? 'border-danger focus-visible:border-danger focus-visible:ring-danger/20'
                    : 'border-border-strong',
                className,
            )}
            {...props}
        />
    );
});
