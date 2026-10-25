import * as Dialog from '@radix-ui/react-dialog';
import { Button } from '@/components/atoms/Button';
import { Spinner } from '@/components/atoms/Spinner';
import { cn } from '@/lib/utils';

export interface CheckinTicket {
    id: number;
    code: string;
    holderName: string;
    sectorName: string;
    seatLabel: string;
}

/** pending = QR válido aguardando a portaria; ok = entrada registrada; denied = barrado. */
export type CheckinOutcome =
    | { kind: 'pending'; ticket: CheckinTicket }
    | { kind: 'ok'; ticket: CheckinTicket | null }
    | { kind: 'denied'; reason: string | null; ticket: CheckinTicket | null };

/** Selo animado: círculo com mola, traço desenhado e onda (ou tremida no negado). */
function Seal({ success }: { success: boolean }) {
    return (
        <div
            className={cn('relative mx-auto size-24', !success && 'kena-shake')}
            aria-hidden="true"
        >
            <span
                className={cn(
                    'kena-seal-ring absolute inset-0 rounded-full',
                    success ? 'bg-success' : 'bg-danger',
                )}
            />
            <span
                className={cn(
                    'kena-seal relative flex size-full items-center justify-center rounded-full text-white shadow-lg',
                    success ? 'bg-success' : 'bg-danger',
                )}
            >
                <svg
                    viewBox="0 0 24 24"
                    className="size-12"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2.6}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    {success ? (
                        <path
                            className="kena-seal-stroke"
                            d="M5 12.5l4.5 4.5L19 7.5"
                        />
                    ) : (
                        <>
                            <path className="kena-seal-stroke" d="M7 7l10 10" />
                            <path className="kena-seal-stroke" d="M17 7L7 17" />
                        </>
                    )}
                </svg>
            </span>
        </div>
    );
}

function TicketDetails({ ticket }: { ticket: CheckinTicket }) {
    return (
        <dl className="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 rounded-btn border border-border bg-bg p-4 text-left font-body text-sm">
            <div className="col-span-2">
                <dt className="kicker text-faint">Titular</dt>
                <dd className="mt-1 text-base font-semibold text-foreground">
                    {ticket.holderName}
                </dd>
            </div>
            <div>
                <dt className="kicker text-faint">Setor</dt>
                <dd className="mt-1 text-foreground">{ticket.sectorName}</dd>
            </div>
            <div>
                <dt className="kicker text-faint">Assento</dt>
                <dd className="mt-1 font-display text-display-sm text-foreground">
                    {ticket.seatLabel}
                </dd>
            </div>
            <div className="col-span-2">
                <dt className="kicker text-faint">Código</dt>
                <dd className="mt-1 font-mono text-xs text-muted-foreground">
                    {ticket.code}
                </dd>
            </div>
        </dl>
    );
}

const copy = {
    pending: {
        title: 'Ingresso válido',
        subtitle: 'Confira o titular e confirme a entrada.',
    },
    ok: { title: 'Entrada liberada', subtitle: 'Pode passar. Bom espetáculo!' },
    denied: { title: 'Acesso negado', subtitle: null },
} as const;

/**
 * Popup da portaria após cada leitura. No QR válido nada é registrado até
 * "Confirmar entrada"; cancelar só fecha e volta a ler.
 */
export function CheckinResultDialog({
    outcome,
    confirming,
    onConfirm,
    onClose,
}: {
    outcome: CheckinOutcome | null;
    confirming: boolean;
    onConfirm: () => void;
    onClose: () => void;
}) {
    const success = outcome?.kind !== 'denied';
    const text = outcome ? copy[outcome.kind] : null;
    const subtitle =
        outcome?.kind === 'denied'
            ? (outcome.reason ?? 'Ingresso não liberado.')
            : text?.subtitle;

    return (
        <Dialog.Root
            open={outcome !== null}
            onOpenChange={(open) => !open && !confirming && onClose()}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-[70] bg-black/60 backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in" />
                <Dialog.Content
                    className={cn(
                        'fixed inset-x-3 top-1/2 z-[70] mx-auto max-h-[calc(100dvh-2rem)] max-w-sm -translate-y-1/2 overflow-y-auto overscroll-contain rounded-[20px] border bg-surface px-5 pt-7 pb-5 text-center shadow-2xl focus:outline-none data-[state=open]:animate-in data-[state=open]:zoom-in-95 data-[state=open]:fade-in',
                        success ? 'border-success/40' : 'border-danger/40',
                    )}
                >
                    {outcome && text && (
                        // key reinicia as animações a cada novo estado (pendente → liberado).
                        <div key={outcome.kind}>
                            <Seal success={success} />
                            <Dialog.Title className="mt-5 font-display text-display-md text-foreground uppercase">
                                {text.title}
                            </Dialog.Title>
                            <Dialog.Description
                                className={cn(
                                    'mt-1 font-body text-sm',
                                    success
                                        ? 'text-muted-foreground'
                                        : 'font-medium text-danger-text',
                                )}
                            >
                                {subtitle}
                            </Dialog.Description>

                            {outcome.ticket && (
                                <TicketDetails ticket={outcome.ticket} />
                            )}

                            {outcome.kind === 'pending' ? (
                                <div className="mt-5 grid grid-cols-2 gap-3">
                                    <Button
                                        variant="secondary"
                                        onClick={onClose}
                                        disabled={confirming}
                                    >
                                        Cancelar
                                    </Button>
                                    <Button
                                        variant="success"
                                        onClick={onConfirm}
                                        disabled={confirming}
                                        autoFocus
                                    >
                                        {confirming ? (
                                            <Spinner size={16} />
                                        ) : (
                                            'Confirmar entrada'
                                        )}
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    block
                                    variant={success ? 'success' : 'secondary'}
                                    className="mt-5"
                                    onClick={onClose}
                                    autoFocus
                                >
                                    Próxima leitura
                                </Button>
                            )}
                        </div>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
