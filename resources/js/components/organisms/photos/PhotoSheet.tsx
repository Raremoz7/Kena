import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

interface PhotoSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** `side`: gaveta à direita no desktop. `center`: diálogo centralizado. No celular os dois sobem de baixo. */
    variant?: 'side' | 'center';
    footer?: ReactNode;
    children: ReactNode;
}

/**
 * Painel das fotos: folha de baixo no celular (alcance do polegar) e gaveta ou
 * diálogo no desktop. Foco preso e Esc fecha, pelo Radix.
 */
export function PhotoSheet({
    open,
    onOpenChange,
    title,
    description,
    variant = 'center',
    footer,
    children,
}: PhotoSheetProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[70] bg-black/55 backdrop-blur-[2px] data-[state=closed]:animate-out data-[state=closed]:fade-out data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content
                    className={cn(
                        'fixed z-[70] flex flex-col border-border bg-surface shadow-2xl focus:outline-none',
                        'data-[state=closed]:animate-out data-[state=closed]:fade-out data-[state=open]:animate-in data-[state=open]:duration-300',
                        // celular: folha de baixo
                        'inset-x-0 bottom-0 max-h-[88dvh] rounded-t-[20px] border-t data-[state=open]:slide-in-from-bottom',
                        variant === 'side'
                            ? 'sm:inset-y-0 sm:right-0 sm:left-auto sm:max-h-none sm:w-[26rem] sm:rounded-none sm:border-t-0 sm:border-l sm:data-[state=open]:slide-in-from-bottom-0 sm:data-[state=open]:slide-in-from-right'
                            : 'sm:inset-x-auto sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-h-[80dvh] sm:w-[40rem] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-card sm:border sm:data-[state=open]:slide-in-from-bottom-0 sm:data-[state=open]:zoom-in-95',
                    )}
                >
                    <div
                        aria-hidden="true"
                        className="mx-auto mt-2.5 h-1 w-10 shrink-0 rounded-full bg-border-strong/50 sm:hidden"
                    />
                    <div className="flex items-start justify-between gap-3 px-5 pt-3 pb-3 sm:pt-5">
                        <div className="min-w-0">
                            <Dialog.Title className="font-display text-display-sm text-foreground uppercase">
                                {title}
                            </Dialog.Title>
                            <Dialog.Description
                                className={cn(
                                    'mt-0.5 font-body text-sm text-muted-foreground',
                                    !description && 'sr-only',
                                )}
                            >
                                {description ?? title}
                            </Dialog.Description>
                        </div>
                        <Dialog.Close
                            aria-label="Fechar"
                            className="-mr-1 inline-flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-surface-2 hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                        >
                            <Icon name="close" size={18} />
                        </Dialog.Close>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-5">
                        {children}
                    </div>
                    {footer && (
                        <div className="border-t border-border px-5 pt-4 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                            {footer}
                        </div>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
