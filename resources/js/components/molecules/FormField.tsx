import { useId } from 'react';
import type { ReactNode } from 'react';
import { FieldContext } from '@/components/atoms/field-context';
import { Label } from '@/components/atoms/Label';
import { cn } from '@/lib/utils';

interface FormFieldProps {
    label: string;
    htmlFor?: string;
    helper?: string;
    error?: string;
    children: ReactNode;
    className?: string;
    /** Conteúdo à direita do label (ex.: link "Esqueci a senha?"). */
    action?: ReactNode;
}

/**
 * Campo de formulário: label acima, helper opcional, erro acionável abaixo.
 * Quando há erro, propaga o estado para o input (borda em danger + aria) via
 * FieldContext, sem precisar passar `invalid` manualmente.
 */
export function FormField({
    label,
    htmlFor,
    helper,
    error,
    children,
    className,
    action,
}: FormFieldProps) {
    const fallbackId = useId();
    const errorId = `${htmlFor ?? fallbackId}-error`;

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {action ? (
                <div className="flex items-center justify-between gap-2">
                    <Label htmlFor={htmlFor}>{label}</Label>
                    {action}
                </div>
            ) : (
                <Label htmlFor={htmlFor}>{label}</Label>
            )}
            <FieldContext.Provider value={{ invalid: !!error, errorId }}>
                {children}
            </FieldContext.Provider>
            {error ? (
                <p id={errorId} className="font-body text-xs text-danger-text">
                    {error}
                </p>
            ) : helper ? (
                <p className="font-body text-xs text-faint">{helper}</p>
            ) : null}
        </div>
    );
}
