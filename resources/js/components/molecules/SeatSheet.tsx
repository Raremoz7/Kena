import { AnimatePresence, motion } from 'framer-motion';
import { useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Spinner } from '@/components/atoms/Spinner';
import type { Seat } from '@/lib/veludo/types';

interface SeatSheetProps {
    seats: Seat[];
    total: number;
    onRemove: (seat: Seat) => void;
    onClear: () => void;
    onCheckout: () => void;
    submitting: boolean;
}

/**
 * Bottom-sheet de seleção (mobile). Recolhida: resumo + "Continuar". Expandida:
 * lista "Sua seleção" + subtotal + CTA. Substitui a barra fixa fina no mobile.
 * Só renderiza abaixo de lg — no desktop a seleção vive no aside.
 */
export function SeatSheet({ seats, total, onRemove, onClear, onCheckout, submitting }: SeatSheetProps) {
    const [open, setOpen] = useState(false);
    const count = seats.length;
    const codes = seats.map((s) => s.code).join(', ');

    return (
        <div className="lg:hidden">
            {/* scrim + folha expandida */}
            <AnimatePresence>
                {open && (
                    <>
                        <motion.div
                            className="fixed inset-0 z-50 bg-black/45"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            onClick={() => setOpen(false)}
                        />
                        <motion.div
                            className="fixed inset-x-0 bottom-0 z-[60] flex max-h-[80vh] flex-col rounded-t-[20px] border-t border-border bg-surface px-4 pt-2.5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-[0_-12px_34px_rgba(0,0,0,0.5)]"
                            initial={{ y: '100%' }}
                            animate={{ y: 0 }}
                            exit={{ y: '100%' }}
                            transition={{ type: 'spring', stiffness: 380, damping: 38 }}
                            drag="y"
                            dragConstraints={{ top: 0, bottom: 0 }}
                            dragElastic={{ top: 0, bottom: 0.4 }}
                            onDragEnd={(_, info) => {
                                if (info.offset.y > 90) {
setOpen(false);
}
                            }}
                        >
                            <button
                                type="button"
                                aria-label="Recolher seleção"
                                onClick={() => setOpen(false)}
                                className="mx-auto mb-3 h-1 w-9 rounded-full bg-border"
                            />
                            <p className="kicker mb-3 text-faint">Sua seleção</p>

                            <ul className="-mx-1 flex flex-col gap-2 overflow-y-auto px-1">
                                {seats.map((s) => (
                                    <li
                                        key={s.id}
                                        className="flex items-center justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-2.5"
                                    >
                                        <p className="min-w-0 font-body text-sm">
                                            <span className="font-display font-semibold text-foreground">{s.code}</span>{' '}
                                            <span className="text-muted-foreground">· {s.sectorName}</span>
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <Price value={s.price} className="text-sm text-muted-foreground" />
                                            <button
                                                type="button"
                                                aria-label={`Remover ${s.code}`}
                                                onClick={() => onRemove(s)}
                                                className="text-faint transition-colors hover:text-danger"
                                            >
                                                <Icon name="close" size={15} />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                                <span className="font-body text-sm text-muted-foreground">Subtotal</span>
                                <Price value={total} className="text-lg text-foreground" />
                            </div>

                            <div className="mt-3 flex flex-col gap-2">
                                <Button variant="success" block onClick={onCheckout} disabled={submitting}>
                                    {submitting ? (
                                        <Spinner />
                                    ) : (
                                        <>
                                            Ir para o checkout
                                            <Icon name="arrow-right" size={18} />
                                        </>
                                    )}
                                </Button>
                                <Button variant="ghost" block size="sm" onClick={onClear} disabled={submitting}>
                                    Limpar seleção
                                </Button>
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* barra recolhida (peek) — acima do tab bar no mobile (<md), no rodapé a partir de md */}
            <div className="fixed inset-x-0 bottom-[60px] z-40 rounded-t-[18px] border-t border-border bg-surface px-4 pt-2 pb-3 shadow-[0_-8px_24px_rgba(0,0,0,0.4)] md:bottom-0 md:pb-[max(1.125rem,env(safe-area-inset-bottom))]">
                <button
                    type="button"
                    aria-label={count > 0 ? 'Abrir seleção' : undefined}
                    disabled={count === 0}
                    onClick={() => count > 0 && setOpen(true)}
                    className="mx-auto mb-2 block h-1 w-9 rounded-full bg-border disabled:opacity-50"
                />
                <div className="flex items-center justify-between gap-3">
                    <button
                        type="button"
                        disabled={count === 0}
                        onClick={() => count > 0 && setOpen(true)}
                        className="min-w-0 text-left"
                    >
                        <span className="block truncate font-body text-xs text-muted-foreground">
                            {count === 0
                                ? 'Nenhum assento'
                                : `${count} ${count === 1 ? 'assento' : 'assentos'} · ${codes}`}
                        </span>
                        <Price value={total} className="text-lg text-foreground" />
                    </button>

                    {count > 0 ? (
                        <Button variant="success" onClick={() => setOpen(true)}>
                            Continuar
                            <Icon name="chevron-right" size={16} className="-rotate-90" />
                        </Button>
                    ) : (
                        <Button variant="success" disabled>
                            Continuar
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
