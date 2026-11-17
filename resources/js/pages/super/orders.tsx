import { Head } from '@inertiajs/react';
import { Badge } from '@/components/atoms/Badge';
import type { BadgeProps } from '@/components/atoms/Badge';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { brl, Filters, Pager } from '@/components/organisms/SuperAdminList';
import type { SimplePage } from '@/components/organisms/SuperAdminList';

interface OrderRow {
    id: number;
    reference: string;
    createdAt: string | null;
    kind: string;
    status: string;
    total: number;
    fee: number;
    organization: string | null;
    event: string | null;
    buyer: { name: string; email: string };
}

interface OrdersProps {
    orders: SimplePage<OrderRow>;
    filters: { q: string; status: string | null; organizacao: number | null };
    statuses: string[];
    organizations: { id: number; name: string }[];
}

const statusInfo: Record<string, { label: string; tone: BadgeProps['tone'] }> =
    {
        pending: { label: 'Pendente', tone: 'warning' },
        paid: { label: 'Pago', tone: 'success' },
        failed: { label: 'Recusado', tone: 'danger' },
        cancelled: { label: 'Cancelado', tone: 'neutral' },
        refunded: { label: 'Estornado', tone: 'info' },
    };

/** Super admin: busca de pedidos em todos os organizadores (só leitura). */
export default function SuperOrders({
    orders,
    filters,
    statuses,
    organizations,
}: OrdersProps) {
    return (
        <>
            <Head title="Admin — Pedidos" />
            <AdminPage>
                <PageHeader
                    title="Pedidos"
                    description={`${orders.meta.total} pedidos. Busca por referência, nome, e-mail ou CPF do comprador. Estornos são feitos pelo painel do organizador.`}
                />
                <Filters
                    url="/admin/pedidos"
                    q={filters.q}
                    placeholder="Referência, nome, e-mail ou CPF"
                    organizations={organizations}
                    organizationId={filters.organizacao}
                    extra={{
                        name: 'status',
                        value: filters.status ?? '',
                        children: (
                            <>
                                <option value="">Todos os status</option>
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {statusInfo[s]?.label ?? s}
                                    </option>
                                ))}
                            </>
                        ),
                    }}
                />

                <ul className="mt-4 divide-y divide-border rounded-card border border-border bg-surface">
                    {orders.data.map((o) => (
                        <li
                            key={o.id}
                            className="flex flex-wrap items-start justify-between gap-2 p-4"
                        >
                            <div className="min-w-0">
                                <p className="font-mono text-sm text-foreground">
                                    {o.reference}
                                </p>
                                <p className="font-body text-xs text-muted-foreground">
                                    {o.buyer.name} · {o.buyer.email}
                                </p>
                                <p className="font-body text-xs text-faint">
                                    {o.organization ?? '—'} · {o.kind}:{' '}
                                    {o.event ?? '—'} · {o.createdAt}
                                </p>
                            </div>
                            <div className="flex flex-col items-end gap-1">
                                <Badge
                                    tone={
                                        statusInfo[o.status]?.tone ?? 'neutral'
                                    }
                                >
                                    {statusInfo[o.status]?.label ?? o.status}
                                </Badge>
                                <span className="font-body text-sm text-foreground tabular-nums">
                                    {brl(o.total)}
                                </span>
                                <span className="font-body text-[11px] text-faint tabular-nums">
                                    taxa {brl(o.fee)}
                                </span>
                            </div>
                        </li>
                    ))}
                    {orders.data.length === 0 && (
                        <li className="p-4 font-body text-sm text-muted-foreground">
                            Nenhum pedido encontrado.
                        </li>
                    )}
                </ul>
                <Pager page={orders} />
            </AdminPage>
        </>
    );
}
