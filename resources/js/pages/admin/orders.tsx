import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import type { BadgeProps } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
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
import { RefundTicketsDialog } from '@/components/organisms/RefundTicketsDialog';
import type { RefundableTicket } from '@/components/organisms/RefundTicketsDialog';
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
    /** Pedido pago com algum ingresso já devolvido avulso. */
    partiallyRefunded: boolean;
    /** Quanto ainda cabe devolver no pedido (reais). */
    remaining: number;
    tickets: RefundableTicket[];
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
    search: string;
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
    search,
    exportUrl,
}: OrdersPageProps) {
    // Busca no servidor (a lista é paginada): filtro e texto viajam juntos na
    // URL, e qualquer mudança volta para a página 1.
    const [query, setQuery] = useState(search);
    const searchTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    function applyFilters(next: { session?: number | null; q?: string }) {
        const session = next.session === undefined ? sessionId : next.session;
        const q = (next.q ?? query).trim();

        router.get(
            '/painel/pedidos',
            {
                ...(session ? { session } : {}),
                ...(q ? { q } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function filterSession(value: string) {
        applyFilters({ session: value ? Number(value) : null });
    }

    function onSearchChange(value: string) {
        setQuery(value);
        clearTimeout(searchTimer.current);
        searchTimer.current = setTimeout(() => applyFilters({ q: value }), 350);
    }

    function clearSearch() {
        clearTimeout(searchTimer.current);
        setQuery('');
        applyFilters({ q: '' });
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

    // Reembolso por assento: o organizador escolhe quais ingressos devolver;
    // marcar todos equivale a reembolsar o pedido inteiro.
    const [refundTarget, setRefundTarget] = useState<OrderRow | null>(null);

    function refundTickets(
        o: OrderRow,
        ticketIds: number[],
        completesOrder: boolean,
    ) {
        setRefundTarget(null);
        router.post(
            `/painel/pedidos/${o.id}/reembolso-ingressos`,
            { ticket_ids: ticketIds },
            {
                preserveScroll: true,
                onError: (e) =>
                    veludoToast.error(
                        'Reembolso não concluído',
                        e.order ?? e.ticket_ids ?? 'Tente novamente.',
                    ),
                onSuccess: () =>
                    veludoToast.success(
                        'Reembolso processado',
                        completesOrder
                            ? `Pedido ${o.reference} reembolsado.`
                            : `${ticketIds.length} ${ticketIds.length === 1 ? 'ingresso' : 'ingressos'} do pedido ${o.reference} ${ticketIds.length === 1 ? 'reembolsado' : 'reembolsados'}.`,
                    ),
            },
        );
    }

    const emptyMessage = search
        ? `Nenhum pedido encontrado para "${search}".`
        : 'Nenhum pedido ainda.';

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
                    <div className="relative w-full sm:w-auto sm:min-w-72">
                        <Icon
                            name="search"
                            size={16}
                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-faint"
                        />
                        <Input
                            type="search"
                            aria-label="Buscar pedidos"
                            placeholder="Nome, e-mail, pedido ou assento"
                            value={query}
                            onChange={(e) => onSearchChange(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape' && query) {
                                    clearSearch();
                                }
                            }}
                            maxLength={100}
                            className="pl-9"
                        />
                    </div>
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
                                    {o.partiallyRefunded && (
                                        <Badge tone="info">
                                            Reembolso parcial
                                        </Badge>
                                    )}
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
                                        onClick={() => setRefundTarget(o)}
                                    >
                                        <Icon name="refund" size={14} />{' '}
                                        Reembolsar
                                    </Button>
                                ) : undefined
                            }
                        />
                    ))}
                    {orders.data.length === 0 && (
                        <MobileListEmpty>{emptyMessage}</MobileListEmpty>
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
                                        {o.partiallyRefunded && (
                                            <Badge tone="info">
                                                Reembolso parcial
                                            </Badge>
                                        )}
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
                                                onClick={() =>
                                                    setRefundTarget(o)
                                                }
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
                                        {emptyMessage}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={orders.links} />
            </AdminPage>
            <RefundTicketsDialog
                key={refundTarget?.id ?? 'none'}
                order={refundTarget}
                open={refundTarget !== null}
                onOpenChange={(open) => !open && setRefundTarget(null)}
                onConfirm={(ticketIds, completesOrder) =>
                    refundTarget &&
                    refundTickets(refundTarget, ticketIds, completesOrder)
                }
            />
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
