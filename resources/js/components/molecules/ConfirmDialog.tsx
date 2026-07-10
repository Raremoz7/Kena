import { Button } from '@/components/atoms/Button';
import { Modal } from '@/components/molecules/Modal';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** Ação destrutiva (vermelho) por padrão. */
    tone?: 'danger' | 'default';
    onConfirm: () => void;
}

/**
 * Diálogo de confirmação do design system — substitui window.confirm() por um
 * Modal Veludo acessível. Uso típico: exclusões e outras ações destrutivas.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    tone = 'danger',
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Modal
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={tone === 'danger' ? 'danger' : 'primary'}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            }
        />
    );
}
