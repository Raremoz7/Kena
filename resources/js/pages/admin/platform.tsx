import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';

interface Organization {
    id: number;
    name: string;
    slug: string;
    owner: { name: string; email: string; verified: boolean } | null;
    createdAt: string | null;
    status: 'active' | 'suspended';
    suspendedReason: string | null;
    feePercent: number | null;
    events: number;
    paidOrders: number;
    gross: number;
    fees: number;
    mpConnected: boolean;
}

interface PlatformProps {
    organizations: Organization[];
    defaultFeePercent: number;
    oauthEnabled: boolean;
}

const brl = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** Ações de uma organização: taxa negociada e suspender/reativar as vendas. */
function OrganizationActions({
    org,
    defaultFeePercent,
}: {
    org: Organization;
    defaultFeePercent: number;
}) {
    const [fee, setFee] = useState(
        org.feePercent !== null ? String(org.feePercent) : '',
    );
    const [suspending, setSuspending] = useState(false);
    const [reason, setReason] = useState('');
    const base = `/painel/plataforma/organizacoes/${org.id}`;

    function saveFee(e: FormEvent) {
        e.preventDefault();
        router.put(
            `${base}/taxa`,
            { fee_percent: fee === '' ? null : fee },
            { preserveScroll: true },
        );
    }

    function suspend(e: FormEvent) {
        e.preventDefault();
        router.post(
            `${base}/suspender`,
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => setSuspending(false),
            },
        );
    }

    return (
        <div className="flex w-full flex-col gap-2">
            <form onSubmit={saveFee} className="flex items-center gap-2">
                <label
                    htmlFor={`fee-${org.id}`}
                    className="shrink-0 font-body text-xs text-muted-foreground"
                >
                    Taxa (%)
                </label>
                <Input
                    id={`fee-${org.id}`}
                    type="number"
                    inputMode="decimal"
                    step="0.1"
                    min="0"
                    max="30"
                    value={fee}
                    onChange={(e) => setFee(e.target.value)}
                    placeholder={`${defaultFeePercent} (padrão)`}
                    className="py-2"
                />
                <Button type="submit" size="sm" variant="secondary">
                    Salvar
                </Button>
            </form>

            {org.status === 'suspended' ? (
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() =>
                        router.post(
                            `${base}/reativar`,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    Reativar vendas
                </Button>
            ) : suspending ? (
                <form onSubmit={suspend} className="flex items-center gap-2">
                    <Input
                        aria-label={`Motivo da suspensão de ${org.name}`}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Motivo (ex.: denúncia de evento falso)"
                        required
                        maxLength={255}
                        className="py-2"
                    />
                    <Button type="submit" size="sm" variant="outline-danger">
                        Suspender
                    </Button>
                </form>
            ) : (
                <Button
                    type="button"
                    size="sm"
                    variant="outline-danger"
                    onClick={() => setSuspending(true)}
                >
                    Suspender vendas
                </Button>
            )}
        </div>
    );
}

/** Plataforma (Somo): todas as organizações, vendas, taxas e Mercado Pago. */
export default function Platform({
    organizations,
    defaultFeePercent,
    oauthEnabled,
}: PlatformProps) {
    const totalFees = organizations.reduce((sum, o) => sum + o.fees, 0);

    return (
        <>
            <Head title="Painel — Organizações" />
            <AdminPage>
                <PageHeader
                    title="Organizações"
                    description={`Todos os organizadores da plataforma. Taxa padrão: ${defaultFeePercent}%. Suspender fecha as vendas na hora; o organizador continua vendo pedidos e estornando.`}
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-card border border-border bg-surface p-4">
                        <p className="kicker text-faint">Organizações</p>
                        <p className="mt-1 font-display text-display-sm text-foreground">
                            {organizations.length}
                        </p>
                    </div>
                    <div className="rounded-card border border-border bg-surface p-4">
                        <p className="kicker text-faint">
                            Taxas em pedidos pagos
                        </p>
                        <p className="mt-1 font-display text-display-sm text-foreground">
                            {brl(totalFees)}
                        </p>
                        <p className="font-body text-[11px] text-faint">
                            Antes de estornos.
                            {!oauthEnabled &&
                                ' Com o OAuth desligado, tudo cai na conta da plataforma.'}
                        </p>
                    </div>
                    <div className="rounded-card border border-border bg-surface p-4">
                        <p className="kicker text-faint">Suspensas</p>
                        <p className="mt-1 font-display text-display-sm text-foreground">
                            {
                                organizations.filter(
                                    (o) => o.status === 'suspended',
                                ).length
                            }
                        </p>
                    </div>
                </div>

                <ul className="mt-6 flex flex-col gap-3">
                    {organizations.map((org) => (
                        <li
                            key={org.id}
                            className="rounded-card border border-border bg-surface p-4 sm:p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-body text-base font-medium text-foreground">
                                        {org.name}
                                    </p>
                                    <p className="truncate font-body text-xs text-muted-foreground">
                                        {org.owner
                                            ? `${org.owner.name} · ${org.owner.email}${org.owner.verified ? '' : ' (e-mail não confirmado)'}`
                                            : 'Sem organizador'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    <Badge
                                        tone={
                                            org.status === 'active'
                                                ? 'success'
                                                : 'danger'
                                        }
                                    >
                                        {org.status === 'active'
                                            ? 'Ativa'
                                            : 'Suspensa'}
                                    </Badge>
                                    {oauthEnabled && (
                                        <Badge
                                            tone={
                                                org.mpConnected
                                                    ? 'info'
                                                    : 'warning'
                                            }
                                        >
                                            {org.mpConnected
                                                ? 'Mercado Pago ok'
                                                : 'Sem Mercado Pago'}
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            <dl className="mt-3 grid grid-cols-2 gap-2 font-body text-xs sm:grid-cols-5">
                                <div>
                                    <dt className="text-faint">Eventos</dt>
                                    <dd className="text-foreground">
                                        {org.events}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-faint">
                                        Pedidos pagos
                                    </dt>
                                    <dd className="text-foreground">
                                        {org.paidOrders}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-faint">Vendido</dt>
                                    <dd className="text-foreground">
                                        {brl(org.gross)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-faint">
                                        Taxa recebida
                                    </dt>
                                    <dd className="text-foreground">
                                        {brl(org.fees)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-faint">Taxa</dt>
                                    <dd className="text-foreground">
                                        {org.feePercent !== null
                                            ? `${org.feePercent}% (negociada)`
                                            : `${defaultFeePercent}% (padrão)`}
                                    </dd>
                                </div>
                            </dl>

                            {org.suspendedReason && (
                                <p className="mt-2 font-body text-xs text-danger-text">
                                    Motivo da suspensão: {org.suspendedReason}
                                </p>
                            )}

                            <div className="mt-4 border-t border-border pt-3 sm:max-w-md">
                                <OrganizationActions
                                    org={org}
                                    defaultFeePercent={defaultFeePercent}
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            </AdminPage>
        </>
    );
}
