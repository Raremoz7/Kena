import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Spinner } from '@/components/atoms/Spinner';
import { PriceSummary } from '@/components/molecules/PriceSummary';
import type { PriceLine, ReservationInfo } from '@/lib/veludo/types';

interface CheckoutPanelProps {
    reservation: ReservationInfo;
    lines: PriceLine[];
    total: number;
    onConfirm: () => void;
    submitting?: boolean;
}

/**
 * Resumo do pedido no checkout: assentos, descontos/taxas e CTA de pagamento.
 */
export function CheckoutPanel({ reservation, lines, total, onConfirm, submitting }: CheckoutPanelProps) {
    return (
        <div className="overflow-hidden rounded-card border border-border bg-surface">
            <div className="h-1 w-full bg-accent" />
            <div className="p-6">
                <p className="kicker text-faint">Seu pedido</p>
                <p className="mt-2 font-display text-display-sm text-foreground uppercase">
                    {reservation.eventTitle}
                </p>
                <p className="font-body text-sm text-muted-foreground">{reservation.sessionLabel}</p>

                <ul className="mt-4 flex flex-col gap-2.5 border-y border-border py-4">
                    {reservation.seats.map((s) => (
                        <li key={s.id} className="flex items-center justify-between gap-3">
                            <span className="font-body text-sm text-foreground">
                                {s.sectorName} ·{' '}
                                <strong className="font-display font-semibold">{s.code}</strong>
                            </span>
                            <Price value={s.price} className="text-sm text-muted-foreground" />
                        </li>
                    ))}
                </ul>

                <div className="mt-4">
                    <PriceSummary lines={lines} total={total} />
                </div>

                <Button block className="mt-6" onClick={onConfirm} disabled={submitting}>
                    {submitting ? (
                        <Spinner />
                    ) : (
                        <>
                            Confirmar pagamento
                            <Icon name="arrow-right" size={18} />
                        </>
                    )}
                </Button>
            </div>
        </div>
    );
}
