import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Input } from '@/components/atoms/Input';
import { FormField } from '@/components/molecules/FormField';
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
    photoFeePercent: number | null;
    events: number;
    paidOrders: number;
    gross: number;
    fees: number;
    mpConnected: boolean;
    mpStatus: 'ok' | 'expiring' | 'reconnect' | 'none';
}

interface PlatformProps {
    organizations: Organization[];
    defaultFeePercent: number;
    defaultPhotoFeePercent: number;
    oauthEnabled: boolean;
}

const mpBadge: Record<
    Organization['mpStatus'],
    { label: string; tone: 'info' | 'warning' | 'danger' }
> = {
    ok: { label: 'Mercado Pago ok', tone: 'info' },
    expiring: { label: 'Reconectar Mercado Pago', tone: 'warning' },
    reconnect: { label: 'Mercado Pago parado', tone: 'danger' },
    none: { label: 'Sem Mercado Pago', tone: 'warning' },
};

const brl = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** Campo de taxa (%) com salvar próprio: ingresso e fotos são independentes. */
function FeeField({
    id,
    label,
    field,
    current,
    fallback,
    url,
}: {
    id: string;
    label: string;
    field: 'fee_percent' | 'photo_fee_percent';
    current: number | null;
    fallback: number;
    url: string;
}) {
    const [value, setValue] = useState(current !== null ? String(current) : '');

    function save(e: FormEvent) {
        e.preventDefault();
        router.put(
            url,
            { [field]: value === '' ? null : value },
            { preserveScroll: true },
        );
    }

    return (
        <form onSubmit={save} className="flex items-center gap-2">
            <label
                htmlFor={id}
                className="w-28 shrink-0 font-body text-xs text-muted-foreground"
            >
                {label}
            </label>
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                step="0.1"
                min="0"
                max="30"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder={`${fallback} (padrão)`}
                className="py-2"
            />
            <Button type="submit" size="sm" variant="secondary">
                Salvar
            </Button>
        </form>
    );
}

/** Ações de uma organização: entrar como, taxas negociadas e suspender/reativar. */
function OrganizationActions({
    org,
    defaultFeePercent,
    defaultPhotoFeePercent,
}: {
    org: Organization;
    defaultFeePercent: number;
    defaultPhotoFeePercent: number;
}) {
    const [suspending, setSuspending] = useState(false);
    const [reason, setReason] = useState('');
    const base = `/admin/organizacoes/${org.id}`;

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
            <Button
                type="button"
                size="sm"
                disabled={org.owner === null}
                onClick={() => router.post(`${base}/entrar`)}
            >
                Entrar como {org.name}
            </Button>
            <FeeField
                id={`fee-${org.id}`}
                label="Taxa ingressos (%)"
                field="fee_percent"
                current={org.feePercent}
                fallback={defaultFeePercent}
                url={`${base}/taxa`}
            />
            <FeeField
                id={`photo-fee-${org.id}`}
                label="Taxa fotos (%)"
                field="photo_fee_percent"
                current={org.photoFeePercent}
                fallback={defaultPhotoFeePercent}
                url={`${base}/taxa`}
            />

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

/**
 * Cria organização + primeiro organizador (para quando o cadastro aberto
 * estiver desligado). Ele recebe o convite por e-mail para criar a senha.
 */
function NewOrganization() {
    const form = useForm({ organization_name: '', name: '', email: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/admin/organizacoes', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="mt-6 grid gap-3 rounded-card border border-border bg-surface p-4 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end"
        >
            <FormField
                label="Organização"
                htmlFor="new-org"
                error={form.errors.organization_name}
            >
                <Input
                    id="new-org"
                    value={form.data.organization_name}
                    onChange={(e) =>
                        form.setData('organization_name', e.target.value)
                    }
                    placeholder="Cia. Teatro Aberto"
                />
            </FormField>
            <FormField
                label="Organizador"
                htmlFor="new-owner"
                error={form.errors.name}
            >
                <Input
                    id="new-owner"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    placeholder="Nome de quem vai usar o painel"
                />
            </FormField>
            <FormField
                label="E-mail"
                htmlFor="new-email"
                error={form.errors.email}
            >
                <Input
                    id="new-email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    placeholder="contato@produtora.com.br"
                />
            </FormField>
            <Button type="submit" disabled={form.processing}>
                Criar e convidar
            </Button>
        </form>
    );
}

/** Super admin: todas as organizações, vendas, taxas e Mercado Pago. */
export default function Platform({
    organizations,
    defaultFeePercent,
    defaultPhotoFeePercent,
    oauthEnabled,
}: PlatformProps) {
    const totalFees = organizations.reduce((sum, o) => sum + o.fees, 0);

    return (
        <>
            <Head title="Admin — Organizações" />
            <AdminPage>
                <PageHeader
                    title="Organizações"
                    description={`Todos os organizadores da plataforma. Taxa padrão: ${defaultFeePercent}% nos ingressos e ${defaultPhotoFeePercent}% nas fotos. Suspender fecha as vendas na hora; o organizador continua vendo pedidos e estornando. "Entrar como" abre o painel do organizador e fica registrado.`}
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

                <NewOrganization />

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
                                            tone={mpBadge[org.mpStatus].tone}
                                        >
                                            {mpBadge[org.mpStatus].label}
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
                                        ingressos{' '}
                                        {org.feePercent ?? defaultFeePercent}%
                                        {org.feePercent !== null &&
                                            ' (negociada)'}{' '}
                                        · fotos{' '}
                                        {org.photoFeePercent ??
                                            defaultPhotoFeePercent}
                                        %
                                        {org.photoFeePercent !== null &&
                                            ' (negociada)'}
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
                                    defaultPhotoFeePercent={
                                        defaultPhotoFeePercent
                                    }
                                />
                            </div>
                        </li>
                    ))}
                </ul>
            </AdminPage>
        </>
    );
}
