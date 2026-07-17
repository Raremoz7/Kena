import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

interface DrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Lido por leitores de tela; use `hideTitle` para não exibi-lo. */
    title: string;
    description?: string;
    /** De qual borda a folha entra. */
    side?: 'left' | 'right';
    hideTitle?: boolean;
    children?: ReactNode;
}

/**
 * Gaveta lateral Veludo sobre Radix Dialog — irmã do Modal, para navegação e
 * listas longas que não cabem num diálogo centralizado. Foco preso, fecha no
 * Esc/overlay, acessível por padrão.
 */
export function Drawer({
    open,
    onOpenChange,
    title,
    description,
    side = 'left',
    hideTitle = false,
    children,
}: DrawerProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[70] bg-black/60 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content
                    className={cn(
                        'fixed inset-y-0 z-[70] flex w-[min(17rem,82vw)] flex-col bg-sidebar shadow-2xl focus:outline-none data-[state=open]:animate-in',
                        side === 'left'
                            ? 'left-0 border-r border-border data-[state=open]:slide-in-from-left'
                            : 'right-0 border-l border-border data-[state=open]:slide-in-from-right',
                    )}
                >
                    <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <Dialog.Title
                            className={cn(
                                'min-w-0 font-display text-base font-semibold tracking-[0.04em] text-foreground uppercase',
                                hideTitle && 'sr-only',
                            )}
                        >
                            {title}
                        </Dialog.Title>
                        <Dialog.Description
                            className={cn(
                                'font-body text-sm text-muted-foreground',
                                (hideTitle || !description) && 'sr-only',
                            )}
                        >
                            {description ?? title}
                        </Dialog.Description>
                        <Dialog.Close
                            aria-label="Fechar menu"
                            className="ml-auto shrink-0 rounded-btn p-1 text-faint transition-colors hover:text-foreground"
                        >
                            <Icon name="close" size={18} />
                        </Dialog.Close>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {children}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
