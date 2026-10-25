import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Countdown } from '@/components/atoms/Countdown';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';

export interface ActiveReservation {
    id: number;
    eventTitle: string;
    sessionLabel: string;
    seats: string[];
    expiresAt: string;
    checkoutUrl: string;
    releaseUrl: string;
}

/**
 * Aviso flutuante de reserva em andamento: aparece em qualquer tela do
 * comprador (menos no próprio checkout) quando ele saiu ou recarregou no meio
 * do pagamento. Retomar volta ao checkout; cancelar libera os assentos. Enquanto
 * ele existe, o servidor não deixa começar outra reserva.
 */
export function ActiveReservationBanner() {
    const { url, props } = usePage();
    const reservation = props.activeReservation ?? null;
    const reduceMotion = useReducedMotion();
    const [confirming, setConfirming] = useState(false);
    const [expired, setExpired] = useState<number | null>(null);

    const path = url.split('?')[0];
    const visible =
        reservation !== null &&
        reservation.id !== expired &&
        path !== reservation.checkoutUrl;

    function cancel() {
        if (!reservation) {
            return;
        }

        setConfirming(false);
        router.delete(reservation.releaseUrl, { preserveScroll: true });
    }

    return (
        <>
            <AnimatePresence>
                {visible && (
                    <motion.aside
                        key={reservation.id}
                        role="status"
                        aria-label="Reserva em andamento"
                        initial={{ y: -16, opacity: 0, scale: 0.98 }}
                        animate={{ y: 0, opacity: 1, scale: 1 }}
                        exit={{ y: -16, opacity: 0, scale: 0.98 }}
                        transition={
                            reduceMotion
                                ? { duration: 0 }
                                : {
                                      type: 'spring',
                                      stiffness: 420,
                                      damping: 34,
                                  }
                        }
                        className="fixed inset-x-3 top-[calc(4.5rem+env(safe-area-inset-top))] z-30 mx-auto max-w-lg rounded-card border border-accent/40 bg-surface p-4 shadow-[0_12px_32px_-8px_rgba(0,0,0,0.35)]"
                    >
                        <div className="flex items-start gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent-text">
                                <Icon name="ticket" size={18} />
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-0.5">
                                    <p className="font-body text-sm font-semibold text-foreground">
                                        Você tem uma reserva em andamento
                                    </p>
                                    <Countdown
                                        expiresAt={reservation.expiresAt}
                                        onExpire={() => {
                                            setExpired(reservation.id);
                                            router.reload({
                                                only: ['activeReservation'],
                                            });
                                        }}
                                        className="text-xs"
                                    />
                                </div>
                                <p className="mt-0.5 truncate font-body text-xs text-muted-foreground">
                                    {reservation.eventTitle} ·{' '}
                                    {reservation.sessionLabel}
                                </p>
                                <p className="truncate font-body text-xs text-faint">
                                    {reservation.seats.length === 1
                                        ? 'Assento'
                                        : 'Assentos'}{' '}
                                    {reservation.seats.join(', ')}
                                </p>
                            </div>
                        </div>
                        <div className="mt-3 grid grid-cols-2 gap-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setConfirming(true)}
                            >
                                Cancelar reserva
                            </Button>
                            <Button asChild size="sm">
                                <Link href={reservation.checkoutUrl}>
                                    Retomar pagamento
                                    <Icon name="arrow-right" size={14} />
                                </Link>
                            </Button>
                        </div>
                    </motion.aside>
                )}
            </AnimatePresence>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Cancelar reserva"
                description="Os assentos voltam a ficar disponíveis para outras pessoas. Se gerou um Pix, ele deixa de valer."
                confirmLabel="Cancelar reserva"
                cancelLabel="Manter"
                onConfirm={cancel}
            />
        </>
    );
}
