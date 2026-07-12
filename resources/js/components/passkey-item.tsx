import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const [isDeleting, setIsDeleting] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const handleDelete = () => {
        setConfirming(false);
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <div className="flex items-center justify-between border-b border-border p-4 last:border-b-0">
            <div className="flex items-center gap-4">
                <div className="flex size-10 shrink-0 items-center justify-center rounded-btn bg-surface-2">
                    <Icon name="lock" size={18} className="text-muted-foreground" />
                </div>
                <div className="space-y-1">
                    <div className="flex items-center gap-2.5">
                        <p className="font-body font-medium text-foreground">{passkey.name}</p>
                        {passkey.authenticator && (
                            <span className="inline-flex items-center gap-1 rounded-badge border border-border bg-surface-2 px-2 py-0.5 font-body text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                {passkey.authenticator}
                            </span>
                        )}
                    </div>
                    <p className="font-body text-sm text-muted-foreground">
                        Adicionada {passkey.created_at_diff}
                        {passkey.last_used_at_diff && (
                            <>
                                <span className="mx-1 text-faint">/</span>
                                Usada {passkey.last_used_at_diff}
                            </>
                        )}
                    </p>
                </div>
            </div>

            <Button
                variant="ghost"
                size="sm"
                disabled={isDeleting}
                onClick={() => setConfirming(true)}
                className="text-danger-text"
            >
                <Icon name="trash" size={16} />
                <span className="sr-only">Remover passkey {passkey.name}</span>
            </Button>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Remover passkey"
                description={`A passkey "${passkey.name}" será removida e não poderá mais ser usada para entrar.`}
                confirmLabel="Remover passkey"
                onConfirm={handleDelete}
            />
        </div>
    );
}
