import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface BottomSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Lido por leitores de tela; use `hideTitle` para não exibi-lo. */
    title: string;
    description?: string;
    hideTitle?: boolean;
    className?: string;
    children?: ReactNode;
}

/**
 * Folha que sobe da borda de baixo, no padrão de app nativo — irmã do Drawer e
 * do Modal sobre Radix Dialog. Foco preso, fecha no Esc/overlay, respeita a área
 * segura do iPhone e nunca passa de 85% da altura: o conteúdo rola por dentro.
 */
export function BottomSheet({
    open,
    onOpenChange,
    title,
    description,
    hideTitle = false,
    className,
    children,
}: BottomSheetProps) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[70] bg-black/50 backdrop-blur-[2px] data-[state=closed]:animate-out data-[state=closed]:fade-out data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content
                    className={cn(
                        'fixed inset-x-0 bottom-0 z-[70] flex max-h-[85dvh] flex-col rounded-t-[20px] border-t border-border bg-surface shadow-[0_-12px_34px_rgba(0,0,0,0.25)] focus:outline-none',
                        'data-[state=closed]:animate-out data-[state=closed]:slide-out-to-bottom data-[state=open]:animate-in data-[state=open]:duration-300 data-[state=open]:slide-in-from-bottom',
                        className,
                    )}
                >
                    <div
                        aria-hidden="true"
                        className="mx-auto mt-2.5 h-1 w-10 shrink-0 rounded-full bg-border-strong/50"
                    />
                    <div className={cn('px-5 pt-3', hideTitle && 'sr-only')}>
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
                    <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        {children}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
