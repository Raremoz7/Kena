import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import { FormField } from '@/components/molecules/FormField';

interface LotRow {
    id: number;
    name: string;
    priceCents: number;
    quantity: number | null;
    used: number;
    remaining: number | null;
    state: 'current' | 'next' | 'sold_out';
}

interface LotsPageProps {
    event: { id: number; title: string; slug: string };
    session: { id: number; label: string };
    lots: LotRow[];
    sectorPriceCents: number;
    seatCount: number;
}

const brl = (cents: number) =>
    (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

const STATE: Record<
    LotRow['state'],
    { label: string; tone: 'success' | 'neutral' | 'danger' }
> = {
    current: { label: 'Vendendo agora', tone: 'success' },
    next: { label: 'Próximo', tone: 'neutral' },
    sold_out: { label: 'Esgotado', tone: 'danger' },
};

function LotForm({
    sessionId,
    lot,
    onDone,
}: {
    sessionId: number;
    lot: LotRow | null;
    onDone: () => void;
}) {
    const form = useForm({
        name: lot?.name ?? '',
        price: lot ? String(lot.priceCents / 100) : '',
        quantity: lot?.quantity != null ? String(lot.quantity) : '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onDone() };

        if (lot) {
            form.put(`/painel/lotes/${lot.id}`, options);
        } else {
            form.post(`/painel/sessoes/${sessionId}/lotes`, options);
        }
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-card border border-border bg-surface p-5"
        >
            <p className="kicker text-faint">
                {lot ? `Editar ${lot.name}` : 'Novo lote'}
            </p>
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField
                    label="Nome"
                    htmlFor="lot-name"
                    error={form.errors.name}
                >
                    <Input
                        id="lot-name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        placeholder="1º lote"
                        required
                    />
                </FormField>
                <FormField
                    label="Preço (R$)"
                    htmlFor="lot-price"
                    error={form.errors.price}
                >
                    <Input
                        id="lot-price"
                        type="number"
                        step="0.01"
                        min="0"
                        value={form.data.price}
                        onChange={(e) => form.setData('price', e.target.value)}
                        placeholder="45,00"
                        required
                    />
                </FormField>
                <FormField
                    label="Ingressos pagos"
                    htmlFor="lot-quantity"
                    error={form.errors.quantity}
                    helper="Vazio = sem limite (até acabar o mapa)."
                >
                    <Input
                        id="lot-quantity"
                        type="number"
                        min="1"
                        value={form.data.quantity}
                        onChange={(e) =>
                            form.setData('quantity', e.target.value)
                        }
                        placeholder="250"
                    />
                </FormField>
            </div>
            <div className="flex justify-end gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={onDone}
                    disabled={form.processing}
                >
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {lot ? 'Salvar lote' : 'Criar lote'}
                </Button>
            </div>
        </form>
    );
}

export default function AdminLots({
    event,
    session,
    lots,
    sectorPriceCents,
    seatCount,
}: LotsPageProps) {
    const [editing, setEditing] = useState<LotRow | 'new' | null>(
        lots.length === 0 ? 'new' : null,
    );
    const [removing, setRemoving] = useState<LotRow | null>(null);

    const limited = lots.reduce((sum, l) => sum + (l.quantity ?? 0), 0);

    return (
        <>
            <Head title={`Lotes · ${event.title}`} />
            <div className="mx-auto max-w-4xl px-6 py-8 sm:px-8">
                <Link
                    href={`/painel/eventos/${event.id}/editar`}
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> {event.title}
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">
                    Lotes
                </h1>
                <p className="mt-1 max-w-prose font-body text-sm text-muted-foreground">
                    {session.label} · {seatCount} lugares no mapa. Cada lote
                    vende a quantidade de ingressos pagos pelo seu preço; quando
                    esgota, o próximo abre sozinho. Cortesias não contam no
                    lote.
                    {lots.length === 0 &&
                        ` Sem lotes, vale o preço do setor (${brl(sectorPriceCents)}).`}
                </p>

                {lots.length > 0 && (
                    <div className="mt-6 overflow-hidden rounded-card border border-border">
                        <table className="w-full font-body text-sm">
                            <thead className="bg-surface-2 text-left text-xs text-faint">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Lote
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Preço
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Vendidos
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Situação
                                    </th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border bg-surface">
                                {lots.map((lot) => (
                                    <tr key={lot.id}>
                                        <td className="px-4 py-3 text-foreground">
                                            {lot.name}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {brl(lot.priceCents)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {lot.used}
                                            <span className="text-faint">
                                                {lot.quantity !== null
                                                    ? ` / ${lot.quantity}`
                                                    : ' · sem limite'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={STATE[lot.state].tone}>
                                                {STATE[lot.state].label}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right whitespace-nowrap">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setEditing(lot)}
                                            >
                                                Editar
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                disabled={lot.used > 0}
                                                onClick={() => setRemoving(lot)}
                                                aria-label={`Remover ${lot.name}`}
                                            >
                                                <Icon name="trash" size={16} />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {limited > seatCount && (
                    <p className="mt-3 font-body text-xs text-warning-text">
                        A soma dos lotes ({limited}) passa dos {seatCount}{' '}
                        lugares do mapa — o último lote vai esgotar antes da
                        quantidade.
                    </p>
                )}

                <div className="mt-6">
                    {editing !== null ? (
                        <LotForm
                            key={editing === 'new' ? 'new' : editing.id}
                            sessionId={session.id}
                            lot={editing === 'new' ? null : editing}
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <Button
                            variant="secondary"
                            onClick={() => setEditing('new')}
                        >
                            <Icon name="plus" size={16} /> Adicionar lote
                        </Button>
                    )}
                </div>
            </div>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remover lote"
                description={
                    removing ? `O lote ${removing.name} será removido.` : ''
                }
                confirmLabel="Remover"
                onConfirm={() => {
                    if (removing) {
                        router.delete(`/painel/lotes/${removing.id}`, {
                            preserveScroll: true,
                            onFinish: () => setRemoving(null),
                        });
                    }
                }}
            />
        </>
    );
}
