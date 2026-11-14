import { useMemo, useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import type { BadgeProps } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Checkbox } from '@/components/atoms/Checkbox';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Modal } from '@/components/molecules/Modal';

export interface RefundableTicket {
    id: number;
    seat: string;
    sector: string;
    holder: string;
    status: string;
    /** Fatia do total pago que cabe a este ingresso (reais). */
    amount: number;
}

export interface RefundableOrder {
    id: number;
    reference: string;
    total: number;
    /** Quanto ainda cabe devolver no pedido (reais). */
    remaining: number;
    tickets: RefundableTicket[];
}

interface RefundTicketsDialogProps {
    order: RefundableOrder | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Confirmação com os ingressos marcados e se o lote fecha o pedido. */
    onConfirm: (ticketIds: number[], completesOrder: boolean) => void;
}

const ticketStatusLabel: Record<string, string> = {
    valid: 'Válido',
    used: 'Usado na portaria',
    transferred: 'Transferido',
    refunded: 'Reembolsado',
    cancelled: 'Cancelado',
};

const ticketStatusTone: Record<string, BadgeProps['tone']> = {
    used: 'neutral',
    transferred: 'info',
    refunded: 'info',
    cancelled: 'neutral',
};

/** Ingressos que ainda contam como "vivos" no pedido: válidos ou já usados. */
function isLive(status: string): boolean {
    return status === 'valid' || status === 'used';
}

/**
 * Reembolso por assento no painel: lista os ingressos do pedido com checkbox,
 * mostra o valor que volta pro comprador antes de confirmar e avisa quando a
 * seleção fecha o pedido inteiro.
 */
export function RefundTicketsDialog({
    order,
    open,
    onOpenChange,
    onConfirm,
}: RefundTicketsDialogProps) {
    // O pai remonta o diálogo por pedido (prop `key`), então a seleção nasce
    // vazia a cada abertura sem precisar de efeito.
    const [selected, setSelected] = useState<number[]>([]);

    const refundable = useMemo(
        () => order?.tickets.filter((t) => t.status === 'valid') ?? [],
        [order],
    );

    const live = useMemo(
        () => order?.tickets.filter((t) => isLive(t.status)) ?? [],
        [order],
    );

    // Marcar todos os ingressos vivos devolve o que ainda falta do pedido —
    // inclusive os centavos de arredondamento que o rateio deixa pra trás.
    const completesOrder =
        selected.length > 0 && live.every((t) => selected.includes(t.id));

    const amount = completesOrder
        ? (order?.remaining ?? 0)
        : refundable
              .filter((t) => selected.includes(t.id))
              .reduce((sum, t) => sum + t.amount, 0);

    const allSelected =
        refundable.length > 0 &&
        refundable.every((t) => selected.includes(t.id));

    function toggle(id: number) {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAll() {
        setSelected(allSelected ? [] : refundable.map((t) => t.id));
    }

    const count = selected.length;

    return (
        <Modal
            open={open}
            onOpenChange={onOpenChange}
            title="Reembolsar ingressos"
            description={
                order
                    ? `Pedido ${order.reference} — marque os assentos que serão devolvidos.`
                    : undefined
            }
            footer={
                <>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button
                        variant="danger"
                        disabled={count === 0}
                        onClick={() => onConfirm(selected, completesOrder)}
                    >
                        <Icon name="refund" size={15} />{' '}
                        {count === 0
                            ? 'Reembolsar'
                            : `Reembolsar ${count} ${count === 1 ? 'ingresso' : 'ingressos'}`}
                    </Button>
                </>
            }
        >
            {order && (
                <div className="space-y-4">
                    {refundable.length > 1 && (
                        <label className="flex cursor-pointer items-center gap-2 font-body text-sm text-muted-foreground">
                            <Checkbox
                                checked={allSelected}
                                onChange={toggleAll}
                                aria-label="Selecionar todos os ingressos válidos"
                            />
                            Selecionar todos os válidos
                        </label>
                    )}

                    <ul className="divide-y divide-border rounded-card border border-border">
                        {order.tickets.map((t) => {
                            const canRefund = t.status === 'valid';
                            const checked = selected.includes(t.id);

                            return (
                                <li key={t.id}>
                                    <label
                                        className={
                                            canRefund
                                                ? 'flex cursor-pointer items-center gap-3 px-3 py-2.5 hover:bg-surface-2'
                                                : 'flex items-center gap-3 px-3 py-2.5 opacity-60'
                                        }
                                    >
                                        <Checkbox
                                            checked={checked}
                                            disabled={!canRefund}
                                            onChange={() => toggle(t.id)}
                                            aria-label={`Reembolsar assento ${t.seat}`}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-body text-sm font-medium text-foreground">
                                                {t.sector} · {t.seat}
                                            </span>
                                            <span className="block truncate font-body text-xs text-faint">
                                                {t.holder}
                                            </span>
                                        </span>
                                        {canRefund ? (
                                            <Price
                                                value={t.amount}
                                                className="text-sm text-foreground"
                                            />
                                        ) : (
                                            <Badge
                                                tone={
                                                    ticketStatusTone[
                                                        t.status
                                                    ] ?? 'neutral'
                                                }
                                            >
                                                {ticketStatusLabel[t.status] ??
                                                    t.status}
                                            </Badge>
                                        )}
                                    </label>
                                </li>
                            );
                        })}
                    </ul>

                    {refundable.length === 0 && (
                        <p className="font-body text-sm text-muted-foreground">
                            Nenhum ingresso deste pedido pode ser reembolsado.
                        </p>
                    )}

                    <div className="flex items-baseline justify-between gap-3 rounded-card bg-surface-2 px-3 py-2.5">
                        <span className="font-body text-sm text-muted-foreground">
                            Volta pro comprador
                        </span>
                        <Price
                            value={amount}
                            className="text-base text-foreground"
                        />
                    </div>

                    <p className="font-body text-xs text-faint">
                        {completesOrder
                            ? 'Com esses assentos o pedido inteiro fica reembolsado e o cupom, se houver, volta a valer.'
                            : 'Valor proporcional ao que foi pago no pedido (cupom e taxa rateados). Os demais ingressos continuam válidos e os assentos devolvidos voltam à venda.'}
                    </p>
                </div>
            )}
        </Modal>
    );
}
