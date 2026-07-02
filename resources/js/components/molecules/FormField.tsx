import type { ReactNode } from 'react';
import { Label } from '@/components/atoms/Label';
import { cn } from '@/lib/utils';

interface FormFieldProps {
    label: string;
    htmlFor?: string;
    helper?: string;
    error?: string;
    children: ReactNode;
    className?: string;
}

/**
 * Campo de formulário: label acima, helper opcional, erro acionável abaixo.
 */
export function FormField({ label, htmlFor, helper, error, children, className }: FormFieldProps) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error ? (
                <p className="font-body text-xs text-danger">{error}</p>
            ) : helper ? (
                <p className="font-body text-xs text-faint">{helper}</p>
            ) : null}
        </div>
    );
}
