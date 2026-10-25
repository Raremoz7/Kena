import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import type { BadgeProps } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Price } from '@/components/atoms/Price';
import { Select } from '@/components/atoms/Select';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import {
    MobileList,
    MobileListEmpty,
    MobileListItem,
} from '@/components/molecules/MobileList';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';
import { veludoToast } from '@/lib/veludo/toast';

interface OrderRow {
    id: number;
    reference: string;
    buyer: string;
    email: string;
    event: string;
    sessionLabel: string;
    seats: string;
    total: number;
    status: string;
    refundFailed: boolean;
    date: string | null;
}

interface SessionOption {
    id: number;
    label: string;
}

interface OrdersPageProps {
    orders: Paginator<OrderRow>;
    sessions: SessionOption[];
    sessionId: number | null;
    exportUrl: string;
}

const statusTone: Record<string, BadgeProps['tone']> = {
    paid: 'success',
    pending: 'warning',
    failed: 'danger',
    cancelled: 'neutral',
    refunded: 'info',
};

const statusLabel: Record<string, string> = {
    paid: 'Pago',
    pending: 'Pendente',
    failed: 'Recusado',
    cancelled: 'Cancelado',
    refunded: 'Reembolsado',
};

export default function AdminOrders({
    orders,
    sessions,
    sessionId,
    exportUrl,
}: OrdersPageProps) {
    function filterSession(value: string) {
        router.get('/painel/pedidos', value ? { session: Number(value) } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const [confirm, setConfirm] = useState<{
        title: string;
        description: string;
        action: () => void;
    } | null>(null);

    function cancelSession() {
        if (!sessionId) {
            return;
        }

        setConfirm({
            title: 'Cancelar sessão',
            description:
                'Todos os pedidos pagos desta sessão serão reembolsados e a sessão sai da venda. Esta ação não pode ser desfeita.',
            action: () =>
                router.post(
                    `/painel/sessoes/${sessionId}/cancelar`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () =>
                            veludoToast.success(
                                'Sessão cancelada',
                                'Os pedidos pagos foram reembolsados.',
                            ),
                        onError: () =>
                            veludoToast.error(
                                'Não foi possível cancelar',
                                'Tente novamente.',
                            ),
                    },
                ),
        });
    }

    function refundOrder(o: OrderRow) {
        setConfirm({
            title: 'Reembolsar pedido',
            description: `O pedido ${o.reference} será reembolsado, os ingressos cancelados e os assentos liberados.`,
            action: () =>
                router.post(
                    `/painel/pedidos/${o.id}/reembolso`,
                    {},
                    {
                        preserveScroll: true,
                        onError: (e) =>
                            veludoToast.error(
                                'Reembolso não concluído',
                                e.order ?? 'Tente novamente.',
                            ),
                        onSuccess: () =>
                            veludoToast.success(
                                'Reembolso processado',
                                `Pedido ${o.reference} reembolsado.`,
                            ),
                    },
                ),
        });
    }

    const sessionActions = sessionId !== null && (
        <>
            <Button asChild variant="secondary" size="sm">
                <a href={`/painel/sessoes/${sessionId}/assentos`}>
                    <Icon name="maximize" size={15} /> Assentos
                </a>
            </Button>
            <Button variant="danger" size="sm" onClick={cancelSession}>
                <Icon name="alert" size={15} /> Cancelar sessão
            </Button>
        </>
    );

    return (
        <>
            <Head title="Painel — Pedidos" />
            <AdminPage>
                <PageHeader
                    title="Pedidos"
                    description="Compras e participantes — filtre por sessão e exporte a lista."
                />

                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Select
                        aria-label="Filtrar por sessão"
                        value={sessionId ?? ''}
                        onChange={(e) => filterSession(e.target.value)}
                        className="w-full sm:w-auto sm:min-w-64"
                    >
                        <option value="">Todas as sessões</option>
                        {sessions.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.label}
                            </option>
                        ))}
                    </Select>
                    <Button asChild variant="secondary" size="sm">
                        <a href={exportUrl}>
                            <Icon name="agenda" size={15} /> Exportar CSV
                        </a>
                    </Button>
                    {sessionActions}
                </div>

                <MobileList>
                    {orders.data.map((o) => (
                        <MobileListItem
                            key={o.id}
                            title={o.buyer}
                            subtitle={o.sessionLabel}
                            meta={
                                <>
                                    <span className="font-mono">
                                        {o.reference}
                                    </span>
                                    {o.seats && <> · {o.seats}</>}
                                    {o.date && <> · {o.date}</>}
                                </>
                            }
                            aside={
                                <>
                                    <Price
                                        value={o.total}
                                        className="text-sm text-foreground"
                                    />
                                    <Badge
                                        tone={statusTone[o.status] ?? 'neutral'}
                                    >
                                        {statusLabel[o.status] ?? o.status}
                                    </Badge>
                                    {o.refundFailed && (
                                        <Badge tone="danger">
                                            Estorno falhou
                                        </Badge>
                                    )}
                                </>
                            }
                            actions={
                                o.status === 'paid' ? (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => refundOrder(o)}
                                    >
                                        <Icon name="refund" size={14} />{' '}
                                        Reembolsar
                                    </Button>
                                ) : undefined
                            }
                        />
                    ))}
                    {orders.data.length === 0 && (
                        <MobileListEmpty>Nenhum pedido ainda.</MobileListEmpty>
                    )}
                </MobileList>

                <div className="hidden overflow-x-auto rounded-card border border-border md:block">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">
                                    Pedido
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Comprador
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Sessão
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Assentos
                                </th>
                                <th className="kicker px-4 py-3 text-right text-faint">
                                    Total
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Status
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Data
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((o) => (
                                <tr
                                    key={o.id}
                                    className="border-b border-border last:border-b-0 hover:bg-surface-2"
                                >
                                    <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                        {o.reference}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="block font-medium text-foreground">
                                            {o.buyer}
                                        </span>
                                        <span className="block text-xs text-faint">
                                            {o.email}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {o.sessionLabel}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {o.seats || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Price
                                            value={o.total}
                                            className="text-sm text-foreground"
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            tone={
                                                statusTone[o.status] ??
                                                'neutral'
                                            }
                                        >
                                            {statusLabel[o.status] ?? o.status}
                                        </Badge>
                                        {o.refundFailed && (
                                            <Badge tone="danger">
                                                Estorno falhou
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-faint">
                                        {o.date ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {o.status === 'paid' && (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => refundOrder(o)}
                                            >
                                                <Icon name="refund" size={14} />{' '}
                                                Reembolsar
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {orders.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Nenhum pedido ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={orders.links} />
            </AdminPage>
            <ConfirmDialog
                open={confirm !== null}
                onOpenChange={(open) => !open && setConfirm(null)}
                title={confirm?.title ?? ''}
                description={confirm?.description}
                confirmLabel="Confirmar"
                onConfirm={() => {
                    confirm?.action();
                    setConfirm(null);
                }}
            />
        </>
    );
}
