import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Checkbox nativo estilizado. `accent-color` pinta o estado marcado com o vinho
 * do Veludo sem precisar de um controle customizado (e sem perder o
 * comportamento nativo de teclado/leitor de tela).
 */
export function Checkbox({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            type="checkbox"
            className={cn(
                'size-4 shrink-0 cursor-pointer rounded-[4px] border border-border-strong bg-bg',
                'accent-[var(--accent)] outline-none',
                'focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-bg',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
