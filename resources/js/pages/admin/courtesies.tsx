import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { Textarea } from '@/components/atoms/Textarea';
import { FormField } from '@/components/molecules/FormField';
import { veludoToast } from '@/lib/veludo/toast';

type Status = 'redeemed' | 'pending' | 'expired';

interface CourtesyRow {
    id: number;
    code: string;
    beneficiary: string | null;
    group: string | null;
    tickets: number;
    expiresAt: string | null;
    status: Status;
    redeemedBy: { name: string; email: string; phone: string | null } | null;
    orderReference: string | null;
    seats: string[];
    redeemedAt: string | null;
}

interface CourtesiesProps {
    events: { id: number; title: string }[];
    eventId: number | null;
    courtesies: CourtesyRow[];
    defaultPrefix: string;
}

const STATUS: Record<
    Status,
    { label: string; tone: 'success' | 'warning' | 'neutral' }
> = {
    redeemed: { label: 'Retirada', tone: 'success' },
    pending: { label: 'Não retirada', tone: 'warning' },
    expired: { label: 'Expirada', tone: 'neutral' },
};

const dateTime = (iso: string) =>
    new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });

function GenerateForm({
    eventId,
    defaultPrefix,
    onDone,
}: {
    eventId: number;
    defaultPrefix: string;
    onDone: () => void;
}) {
    const form = useForm({
        event_id: eventId,
        people: '',
        tickets: '2',
        expires_at: '',
        prefix: defaultPrefix,
    });

    const lines = form.data.people
        .split('\n')
        .filter((l) => l.trim() !== '').length;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/painel/cortesias', {
            preserveScroll: true,
            onSuccess: () => onDone(),
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-4 rounded-card border border-border bg-surface p-5"
        >
            <p className="kicker text-faint">Gerar cortesias</p>
            <FormField
                label="Nomes (um por linha)"
                htmlFor="people"
                error={form.errors.people}
                helper='Formato "Nome; Escola". Dá para colar direto de uma planilha (nome e escola em colunas).'
            >
                <Textarea
                    id="people"
                    rows={8}
                    value={form.data.people}
                    onChange={(e) => form.setData('people', e.target.value)}
                    placeholder={
                        'Ana Clara Souza; Escola Pequenas Sapatilhas\nBeatriz Lima; Studio Arte em Movimento'
                    }
                />
            </FormField>
            <div className="grid gap-4 sm:grid-cols-3">
                <FormField
                    label="Ingressos por código"
                    htmlFor="tickets"
                    error={form.errors.tickets}
                >
                    <Input
                        id="tickets"
                        type="number"
                        min="1"
                        max="10"
                        value={form.data.tickets}
                        onChange={(e) =>
                            form.setData('tickets', e.target.value)
                        }
                    />
                </FormField>
                <FormField
                    label="Vale até"
                    htmlFor="expires_at"
                    error={form.errors.expires_at}
                    helper="Depois desse dia o código para de funcionar."
                >
                    <Input
                        id="expires_at"
                        type="date"
                        value={form.data.expires_at}
                        onChange={(e) =>
                            form.setData('expires_at', e.target.value)
                        }
                    />
                </FormField>
                <FormField
                    label="Prefixo do código"
                    htmlFor="prefix"
                    error={form.errors.prefix}
                    helper="Ex.: QN-7Q2XJ1"
                >
                    <Input
                        id="prefix"
                        value={form.data.prefix}
                        onChange={(e) =>
                            form.setData('prefix', e.target.value.toUpperCase())
                        }
                        className="uppercase"
                        maxLength={8}
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
                <Button type="submit" disabled={form.processing || lines === 0}>
                    Gerar {lines > 0 ? lines : ''}{' '}
                    {lines === 1 ? 'cortesia' : 'cortesias'}
                </Button>
            </div>
        </form>
    );
}

export default function AdminCourtesies({
    events,
    eventId,
    courtesies,
    defaultPrefix,
}: CourtesiesProps) {
    const [generating, setGenerating] = useState(false);
    const [filter, setFilter] = useState<Status | 'all'>('all');
    const [group, setGroup] = useState<string>('');

    const groups = useMemo(
        () =>
            Array.from(
                new Set(
                    courtesies
                        .map((c) => c.group)
                        .filter((g): g is string => !!g),
                ),
            ).sort(),
        [courtesies],
    );
    const counts = useMemo(
        () => ({
            all: courtesies.length,
            redeemed: courtesies.filter((c) => c.status === 'redeemed').length,
            pending: courtesies.filter((c) => c.status === 'pending').length,
            expired: courtesies.filter((c) => c.status === 'expired').length,
        }),
        [courtesies],
    );
    const rows = courtesies.filter(
        (c) =>
            (filter === 'all' || c.status === filter) &&
            (group === '' || c.group === group),
    );

    async function copyPending() {
        const pending = courtesies.filter(
            (c) =>
                c.status === 'pending' && (group === '' || c.group === group),
        );
        const text = pending
            .map(
                (c) =>
                    `${c.beneficiary ?? ''}${c.group ? ` (${c.group})` : ''}: ${c.code}`,
            )
            .join('\n');

        try {
            await navigator.clipboard.writeText(text);
            veludoToast.success(
                'Lista copiada',
                `${pending.length} cortesia(s) não retirada(s).`,
            );
        } catch {
            veludoToast.error(
                'Não foi possível copiar',
                'Use a exportação para planilha.',
            );
        }
    }

    return (
        <>
            <Head title="Painel — Cortesias" />
            <div className="px-6 py-8 sm:px-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-display-lg text-foreground uppercase">
                            Cortesias
                        </h1>
                        <p className="mt-1 max-w-prose font-body text-sm text-muted-foreground">
                            Um código por pessoa, que vale ingressos grátis até
                            o prazo. Acompanhe quem já retirou para avisar os
                            pais que faltam.
                        </p>
                    </div>
                    {events.length > 1 && (
                        <Select
                            aria-label="Evento"
                            value={eventId ?? ''}
                            onChange={(e) =>
                                router.get('/painel/cortesias', {
                                    event: e.target.value,
                                })
                            }
                            className="w-64"
                        >
                            {events.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.title}
                                </option>
                            ))}
                        </Select>
                    )}
                </div>

                {eventId === null ? (
                    <p className="mt-6 font-body text-sm text-muted-foreground">
                        Cadastre um evento primeiro.
                    </p>
                ) : (
                    <>
                        <div className="mt-6">
                            {generating ? (
                                <GenerateForm
                                    eventId={eventId}
                                    defaultPrefix={defaultPrefix}
                                    onDone={() => setGenerating(false)}
                                />
                            ) : (
                                <div className="flex flex-wrap gap-3">
                                    <Button onClick={() => setGenerating(true)}>
                                        <Icon name="plus" size={16} /> Gerar
                                        cortesias
                                    </Button>
                                    {courtesies.length > 0 && (
                                        <>
                                            <Button
                                                variant="secondary"
                                                onClick={copyPending}
                                            >
                                                Copiar não retiradas
                                            </Button>
                                            <Button asChild variant="secondary">
                                                <a
                                                    href={`/painel/cortesias/exportar?event=${eventId}`}
                                                >
                                                    Exportar planilha
                                                </a>
                                            </Button>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>

                        {courtesies.length > 0 && (
                            <>
                                <div className="mt-6 flex flex-wrap items-center gap-2">
                                    {(
                                        [
                                            'all',
                                            'pending',
                                            'redeemed',
                                            'expired',
                                        ] as const
                                    ).map((key) => (
                                        <Button
                                            key={key}
                                            size="sm"
                                            variant={
                                                filter === key
                                                    ? 'primary'
                                                    : 'secondary'
                                            }
                                            onClick={() => setFilter(key)}
                                        >
                                            {key === 'all'
                                                ? 'Todas'
                                                : STATUS[key].label}{' '}
                                            ({counts[key]})
                                        </Button>
                                    ))}
                                    {groups.length > 0 && (
                                        <Select
                                            aria-label="Grupo"
                                            value={group}
                                            onChange={(e) =>
                                                setGroup(e.target.value)
                                            }
                                            className="w-56"
                                        >
                                            <option value="">
                                                Todos os grupos
                                            </option>
                                            {groups.map((g) => (
                                                <option key={g} value={g}>
                                                    {g}
                                                </option>
                                            ))}
                                        </Select>
                                    )}
                                </div>

                                <div className="mt-4 overflow-x-auto rounded-card border border-border">
                                    <table className="w-full font-body text-sm">
                                        <thead className="bg-surface-2 text-left text-xs text-faint">
                                            <tr>
                                                <th className="px-4 py-3 font-medium">
                                                    Nome
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Código
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Situação
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Retirado por
                                                </th>
                                                <th className="px-4 py-3 font-medium">
                                                    Assentos
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border bg-surface">
                                            {rows.map((c) => (
                                                <tr key={c.id}>
                                                    <td className="px-4 py-3">
                                                        <p className="text-foreground">
                                                            {c.beneficiary ??
                                                                '—'}
                                                        </p>
                                                        {c.group && (
                                                            <p className="text-xs text-faint">
                                                                {c.group}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                                                        {c.code}
                                                        <p className="font-body text-faint">
                                                            {c.tickets} ingresso
                                                            {c.tickets === 1
                                                                ? ''
                                                                : 's'}
                                                            {c.expiresAt &&
                                                                ` · até ${new Date(c.expiresAt).toLocaleDateString('pt-BR')}`}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge
                                                            tone={
                                                                STATUS[c.status]
                                                                    .tone
                                                            }
                                                        >
                                                            {
                                                                STATUS[c.status]
                                                                    .label
                                                            }
                                                        </Badge>
                                                        {c.redeemedAt && (
                                                            <p className="mt-1 text-xs text-faint">
                                                                {dateTime(
                                                                    c.redeemedAt,
                                                                )}
                                                            </p>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {c.redeemedBy ? (
                                                            <>
                                                                <p className="text-foreground">
                                                                    {
                                                                        c
                                                                            .redeemedBy
                                                                            .name
                                                                    }
                                                                </p>
                                                                <p className="text-xs text-faint">
                                                                    {
                                                                        c
                                                                            .redeemedBy
                                                                            .email
                                                                    }
                                                                    {c
                                                                        .redeemedBy
                                                                        .phone &&
                                                                        ` · ${c.redeemedBy.phone}`}
                                                                </p>
                                                            </>
                                                        ) : (
                                                            <span className="text-faint">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs whitespace-nowrap">
                                                        {c.seats.length > 0
                                                            ? c.seats.join(', ')
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
