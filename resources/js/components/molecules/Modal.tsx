import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';

interface ModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children?: ReactNode;
    footer?: ReactNode;
}

/**
 * Modal Veludo sobre Radix Dialog — usar com parcimônia, só quando não há lugar
 * melhor. Foco preso, fecha no Esc/overlay, acessível por padrão.
 */
export function Modal({ open, onOpenChange, title, description, children, footer }: ModalProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[70] bg-black/60 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content className="fixed top-1/2 left-1/2 z-[70] w-[min(460px,92vw)] -translate-x-1/2 -translate-y-1/2 rounded-card border border-border bg-surface p-7 shadow-2xl focus:outline-none data-[state=open]:animate-in data-[state=open]:fade-in data-[state=open]:zoom-in-95">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <Dialog.Title className="font-display text-display-sm uppercase text-foreground">
                                {title}
                            </Dialog.Title>
                            {description && (
                                <Dialog.Description className="mt-1 font-body text-sm text-muted-foreground">
                                    {description}
                                </Dialog.Description>
                            )}
                        </div>
                        <Dialog.Close
                            aria-label="Fechar"
                            className="rounded-btn p-1 text-faint transition-colors hover:text-foreground"
                        >
                            <Icon name="close" size={18} />
                        </Dialog.Close>
                    </div>
                    {children && <div className="mt-5">{children}</div>}
                    {footer && <div className="mt-6 flex justify-end gap-3">{footer}</div>}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
