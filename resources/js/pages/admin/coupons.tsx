import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { ConfirmDialog } from '@/components/molecules/ConfirmDialog';
import {
    MobileList,
    MobileListEmpty,
    MobileListItem,
} from '@/components/molecules/MobileList';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';

interface CouponRow {
    id: number;
    code: string;
    type: string;
    valueLabel: string;
    used: number;
    maxUses: number | null;
    active: boolean;
    expired: boolean;
    event: string;
    expiresAt: string | null;
}

function UsageCell({
    used,
    maxUses,
    exhausted,
}: {
    used: number;
    maxUses: number | null;
    exhausted: boolean;
}) {
    // Sem limite: mostra só a contagem de resgates.
    if (maxUses === null) {
        return (
            <div className="min-w-[9rem]">
                <p className="font-body text-sm text-foreground tabular-nums">
                    {used}{' '}
                    <span className="text-faint">
                        resgatado{used === 1 ? '' : 's'}
                    </span>
                </p>
                <p className="mt-0.5 font-body text-[11px] text-faint">
                    Sem limite
                </p>
            </div>
        );
    }

    const percent =
        maxUses > 0 ? Math.min(100, Math.round((used / maxUses) * 100)) : 0;
    const near = !exhausted && percent >= 80;
    const barColor = exhausted
        ? 'bg-danger'
        : near
          ? 'bg-warning'
          : 'bg-accent';

    return (
        <div className="min-w-[9rem]">
            <p className="font-body text-sm text-foreground tabular-nums">
                {used}
                <span className="text-faint">/{maxUses}</span>{' '}
                <span className="text-faint">resgatados</span>
            </p>
            <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                <div
                    className={`h-full rounded-full ${barColor}`}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

function CouponStatus({
    coupon,
    exhausted,
}: {
    coupon: CouponRow;
    exhausted: boolean;
}) {
    if (coupon.expired) {
        return <Badge tone="neutral">Expirado</Badge>;
    }

    if (exhausted) {
        return <Badge tone="danger">Esgotado</Badge>;
    }

    return coupon.active ? (
        <Badge tone="success">Ativo</Badge>
    ) : (
        <Badge tone="neutral">Inativo</Badge>
    );
}

export default function AdminCoupons({
    coupons,
}: {
    coupons: Paginator<CouponRow>;
}) {
    const [removing, setRemoving] = useState<CouponRow | null>(null);

    return (
        <>
            <Head title="Painel — Cupons" />
            <AdminPage>
                <PageHeader
                    title="Cupons"
                    description="Códigos de desconto aplicados no checkout."
                    primary={{
                        label: 'Novo cupom',
                        href: '/painel/cupons/novo',
                    }}
                />

                <MobileList>
                    {coupons.data.map((c) => {
                        const exhausted =
                            c.maxUses !== null && c.used >= c.maxUses;

                        return (
                            <MobileListItem
                                key={c.id}
                                href={`/painel/cupons/${c.id}/editar`}
                                title={
                                    <span className="font-mono font-semibold">
                                        {c.code}
                                    </span>
                                }
                                subtitle={`${c.valueLabel} · ${c.event}`}
                                meta={
                                    <>
                                        <UsageCell
                                            used={c.used}
                                            maxUses={c.maxUses}
                                            exhausted={exhausted}
                                        />
                                        {c.expiresAt && (
                                            <p className="mt-1">
                                                Expira {c.expiresAt}
                                            </p>
                                        )}
                                    </>
                                }
                                aside={
                                    <CouponStatus
                                        coupon={c}
                                        exhausted={exhausted}
                                    />
                                }
                                actions={
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setRemoving(c)}
                                        className="text-faint hover:text-danger-text"
                                    >
                                        <Icon name="trash" size={15} /> Excluir
                                    </Button>
                                }
                            />
                        );
                    })}
                    {coupons.data.length === 0 && (
                        <MobileListEmpty>
                            Nenhum cupom criado ainda.
                        </MobileListEmpty>
                    )}
                </MobileList>

                <div className="hidden overflow-x-auto rounded-card border border-border md:block">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">
                                    Código
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Desconto
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Usos
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Evento
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Expira
                                </th>
                                <th className="kicker px-4 py-3 text-faint">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {coupons.data.map((c) => {
                                const exhausted =
                                    c.maxUses !== null && c.used >= c.maxUses;

                                return (
                                    <tr
                                        key={c.id}
                                        className="border-b border-border last:border-b-0 hover:bg-surface-2"
                                    >
                                        <td className="px-4 py-3 font-mono font-semibold text-foreground">
                                            {c.code}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {c.valueLabel}
                                        </td>
                                        <td className="px-4 py-3">
                                            <UsageCell
                                                used={c.used}
                                                maxUses={c.maxUses}
                                                exhausted={exhausted}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {c.event}
                                        </td>
                                        <td className="px-4 py-3 text-faint">
                                            {c.expiresAt ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <CouponStatus
                                                coupon={c}
                                                exhausted={exhausted}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link
                                                        href={`/painel/cupons/${c.id}/editar`}
                                                    >
                                                        <Icon
                                                            name="eye"
                                                            size={15}
                                                        />{' '}
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setRemoving(c)
                                                    }
                                                    aria-label={`Excluir ${c.code}`}
                                                >
                                                    <Icon
                                                        name="trash"
                                                        size={15}
                                                    />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {coupons.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        Nenhum cupom criado ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={coupons.links} />
            </AdminPage>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Excluir cupom"
                description={
                    removing ? `O cupom ${removing.code} será excluído.` : ''
                }
                confirmLabel="Excluir"
                onConfirm={() => {
                    if (removing) {
                        router.delete(`/painel/cupons/${removing.id}`, {
                            preserveScroll: true,
                        });
                    }

                    setRemoving(null);
                }}
            />
        </>
    );
}
