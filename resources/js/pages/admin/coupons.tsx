import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';

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

function UsageCell({ used, maxUses, exhausted }: { used: number; maxUses: number | null; exhausted: boolean }) {
    // Sem limite: mostra só a contagem de resgates.
    if (maxUses === null) {
        return (
            <div className="min-w-[9rem]">
                <p className="font-body text-sm text-foreground tabular-nums">
                    {used} <span className="text-faint">resgatado{used === 1 ? '' : 's'}</span>
                </p>
                <p className="mt-0.5 font-body text-[11px] text-faint">Sem limite</p>
            </div>
        );
    }

    const percent = maxUses > 0 ? Math.min(100, Math.round((used / maxUses) * 100)) : 0;
    const near = !exhausted && percent >= 80;
    const barColor = exhausted ? 'bg-danger' : near ? 'bg-warning' : 'bg-accent';

    return (
        <div className="min-w-[9rem]">
            <p className="font-body text-sm text-foreground tabular-nums">
                {used}
                <span className="text-faint">/{maxUses}</span>{' '}
                <span className="text-faint">resgatados</span>
            </p>
            <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                <div className={`h-full rounded-full ${barColor}`} style={{ width: `${percent}%` }} />
            </div>
        </div>
    );
}

export default function AdminCoupons({ coupons }: { coupons: CouponRow[] }) {
    function remove(c: CouponRow) {
        if (window.confirm(`Excluir o cupom ${c.code}?`)) {
            router.delete(`/dashboard/cupons/${c.id}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Painel — Cupons" />
            <div className="px-6 py-8 sm:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="font-display text-display-lg text-foreground uppercase">Cupons</h1>
                        <p className="mt-1 font-body text-sm text-muted-foreground">
                            Códigos de desconto aplicados no checkout.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/cupons/novo">
                            <Icon name="plus" size={18} /> Novo cupom
                        </Link>
                    </Button>
                </div>

                <div className="mt-6 overflow-x-auto rounded-card border border-border">
                    <table className="w-full border-collapse font-body text-sm">
                        <thead>
                            <tr className="border-b border-border bg-surface-2 text-left">
                                <th className="kicker px-4 py-3 text-faint">Código</th>
                                <th className="kicker px-4 py-3 text-faint">Desconto</th>
                                <th className="kicker px-4 py-3 text-faint">Usos</th>
                                <th className="kicker px-4 py-3 text-faint">Evento</th>
                                <th className="kicker px-4 py-3 text-faint">Expira</th>
                                <th className="kicker px-4 py-3 text-faint">Status</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {coupons.map((c) => {
                                const exhausted = c.maxUses !== null && c.used >= c.maxUses;
                                return (
                                <tr key={c.id} className="border-b border-border last:border-b-0 hover:bg-surface-2">
                                    <td className="px-4 py-3 font-mono font-semibold text-foreground">{c.code}</td>
                                    <td className="px-4 py-3 text-muted-foreground">{c.valueLabel}</td>
                                    <td className="px-4 py-3">
                                        <UsageCell used={c.used} maxUses={c.maxUses} exhausted={exhausted} />
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">{c.event}</td>
                                    <td className="px-4 py-3 text-faint">{c.expiresAt ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        {c.expired ? (
                                            <Badge tone="neutral">Expirado</Badge>
                                        ) : exhausted ? (
                                            <Badge tone="danger">Esgotado</Badge>
                                        ) : c.active ? (
                                            <Badge tone="success">Ativo</Badge>
                                        ) : (
                                            <Badge tone="neutral">Inativo</Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button asChild size="sm" variant="ghost">
                                                <Link href={`/dashboard/cupons/${c.id}/editar`}>
                                                    <Icon name="eye" size={15} /> Editar
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => remove(c)}
                                                aria-label={`Excluir ${c.code}`}
                                            >
                                                <Icon name="trash" size={15} />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                );
                            })}
                            {coupons.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                                        Nenhum cupom criado ainda.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
