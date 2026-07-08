import { forwardRef, useContext } from 'react';
import type { TextareaHTMLAttributes } from 'react';
import { FieldContext } from '@/components/atoms/field-context';
import { cn } from '@/lib/utils';

export interface TextareaProps
    extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    invalid?: boolean;
}

/**
 * Textarea do Veludo. Mesmo tratamento de foco/erro do Input; herda o estado de
 * erro do FormField via FieldContext.
 */
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
    function Textarea(
        { className, invalid, 'aria-describedby': describedBy, ...props },
        ref,
    ) {
        const field = useContext(FieldContext);
        const isInvalid = invalid ?? field.invalid;

        return (
            <textarea
                ref={ref}
                aria-invalid={isInvalid || undefined}
                aria-describedby={
                    describedBy ?? (isInvalid ? field.errorId : undefined)
                }
                className={cn(
                    'w-full rounded-input border bg-bg px-[14px] py-3 font-body text-sm text-foreground',
                    'placeholder:text-faint transition-colors outline-none',
                    'focus-visible:border-accent focus-visible:ring-[3px] focus-visible:ring-accent/20',
                    'disabled:cursor-not-allowed disabled:opacity-50',
                    isInvalid
                        ? 'border-danger focus-visible:border-danger focus-visible:ring-danger/20'
                        : 'border-border',
                    className,
                )}
                {...props}
            />
        );
    },
);
