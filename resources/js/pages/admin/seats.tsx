import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Select } from '@/components/atoms/Select';
import { Spinner } from '@/components/atoms/Spinner';
import { Modal } from '@/components/molecules/Modal';
import { SeatMap } from '@/components/organisms/SeatMap';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';
import type { Seat, SeatMapData, SeatStatus } from '@/lib/veludo/types';

interface SeatsAdminProps {
    event: { title: string; slug: string };
    session: { id: number; label: string };
    seatMap: SeatMapData;
    toggleUrl: string;
    bulkUrl: string;
    issueUrl: string;
}

type Statuses = Record<number, SeatStatus>;

const EDITABLE: SeatStatus[] = ['available', 'blocked'];

function IssueModal({
    open,
    seats,
    issueUrl,
    onClose,
    onIssued,
}: {
    open: boolean;
    seats: Seat[];
    issueUrl: string;
    onClose: () => void;
    onIssued: (statuses: Statuses) => void;
}) {
    const [rows, setRows] = useState<
        Record<number, { name: string; email: string }>
    >({});
    const [sameEmail, setSameEmail] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const row = (id: number) => rows[id] ?? { name: '', email: '' };
    const setRow = (
        id: number,
        patch: Partial<{ name: string; email: string }>,
    ) => setRows((r) => ({ ...r, [id]: { ...row(id), ...patch } }));

    async function submit(e: FormEvent) {
        e.preventDefault();
        setSending(true);
        setError(null);

        try {
            const res = await api.post<{ message: string; statuses: Statuses }>(
                issueUrl,
                {
                    tickets: seats.map((s) => ({
                        session_seat_id: s.id,
                        name: row(s.id).name.trim(),
                        email: (row(s.id).email || sameEmail).trim(),
                    })),
                },
            );
            veludoToast.success('Ingressos emitidos', res.message);
            setRows({});
            setSameEmail('');
            onIssued(res.statuses);
        } catch (err) {
            setError(
                err instanceof ApiError
                    ? err.message
                    : 'Não foi possível emitir os ingressos.',
            );
        } finally {
            setSending(false);
        }
    }

    return (
        <Modal
            open={open}
            onOpenChange={(o) => !o && !sending && onClose()}
            title={`Emitir ${seats.length} ingresso${seats.length === 1 ? '' : 's'}`}
            description="Grátis e nominais. Cada e-mail recebe os próprios ingressos com QR; o lugar sai da venda."
        >
            <form onSubmit={submit} className="flex flex-col gap-4">
                <label className="flex flex-col gap-1.5 font-body text-sm">
                    <span className="text-muted-foreground">
                        E-mail para todos (opcional)
                    </span>
                    <Input
                        type="email"
                        value={sameEmail}
                        onChange={(e) => setSameEmail(e.target.value)}
                        placeholder="gestao@escola.com"
                    />
                    <span className="text-xs text-faint">
                        Usado nas linhas sem e-mail próprio.
                    </span>
                </label>

                <div className="flex max-h-80 flex-col gap-2 overflow-y-auto pr-1">
                    {seats.map((s) => (
                        <div
                            key={s.id}
                            className="grid grid-cols-[3.5rem_1fr_1fr] items-center gap-2"
                        >
                            <span className="font-display text-sm font-semibold text-foreground">
                                {s.code}
                            </span>
                            <Input
                                aria-label={`Nome no ingresso ${s.code}`}
                                value={row(s.id).name}
                                onChange={(e) =>
                                    setRow(s.id, { name: e.target.value })
                                }
                                placeholder="Nome"
                                required
                            />
                            <Input
                                aria-label={`E-mail do ingresso ${s.code}`}
                                type="email"
                                value={row(s.id).email}
                                onChange={(e) =>
                                    setRow(s.id, { email: e.target.value })
                                }
                                placeholder={sameEmail || 'E-mail'}
                                required={sameEmail.trim() === ''}
                            />
                        </div>
                    ))}
                </div>

                {error && (
                    <p className="font-body text-xs text-danger-text">
                        {error}
                    </p>
                )}

                <div className="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onClose}
                        disabled={sending}
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={sending}>
                        {sending ? <Spinner /> : 'Emitir ingressos'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function AdminSeats({
    event,
    session,
    seatMap,
    toggleUrl,
    bulkUrl,
    issueUrl,
}: SeatsAdminProps) {
    const [statuses, setStatuses] = useState<Statuses>(() =>
        Object.fromEntries(seatMap.seats.map((s) => [s.id, s.status])),
    );
    const [selecting, setSelecting] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const [row, setRow] = useState('');
    const [busy, setBusy] = useState(false);
    const [issuing, setIssuing] = useState(false);

    const seats = useMemo(
        () =>
            seatMap.seats.map((s) => ({
                ...s,
                status: statuses[s.id] ?? s.status,
            })),
        [seatMap.seats, statuses],
    );
    const rows = useMemo(
        () => Array.from(new Set(seatMap.seats.map((s) => s.row))).sort(),
        [seatMap.seats],
    );
    const selectedSeats = seats.filter((s) => selected.includes(s.id));
    const blocked = seats.filter((s) => s.status === 'blocked').length;

    async function toggle(seat: Seat) {
        const current = statuses[seat.id] ?? seat.status;

        if (!EDITABLE.includes(current)) {
            return;
        }

        // Modo seleção: só marca; as ações ficam na barra.
        if (selecting) {
            setSelected((sel) =>
                sel.includes(seat.id)
                    ? sel.filter((id) => id !== seat.id)
                    : [...sel, seat.id],
            );

            return;
        }

        try {
            const res = await api.post<{ id: number; status: SeatStatus }>(
                toggleUrl,
                { session_seat_id: seat.id },
            );
            setStatuses((s) => ({ ...s, [res.id]: res.status }));
        } catch (e) {
            veludoToast.error(
                'Erro',
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível alterar o assento.',
            );
        }
    }

    function selectRow() {
        const ids = seats
            .filter((s) => s.row === row && EDITABLE.includes(s.status))
            .map((s) => s.id);
        setSelected((sel) => Array.from(new Set([...sel, ...ids])));
    }

    async function bulk(action: 'block' | 'release') {
        setBusy(true);

        try {
            const res = await api.post<{ changed: number; statuses: Statuses }>(
                bulkUrl,
                {
                    session_seat_ids: selected,
                    action,
                },
            );
            setStatuses((s) => ({ ...s, ...res.statuses }));
            setSelected([]);
            veludoToast.success(
                action === 'block' ? 'Lugares bloqueados' : 'Lugares liberados',
                `${res.changed} lugar(es) alterado(s).`,
            );
        } catch (e) {
            veludoToast.error(
                'Erro',
                e instanceof ApiError
                    ? e.message
                    : 'Não foi possível alterar os lugares.',
            );
        } finally {
            setBusy(false);
        }
    }

    return (
        <>
            <Head title={`Assentos · ${event.title}`} />
            <div className="px-4 py-6 sm:px-8 sm:py-8">
                <Link
                    href="/painel/pedidos"
                    className="-ml-1 inline-flex min-h-9 items-center gap-1 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Painel
                </Link>
                <h1 className="mt-2 font-display text-display-md text-foreground uppercase sm:text-display-lg">
                    Assentos
                </h1>
                <p className="mt-1 max-w-prose font-body text-sm text-muted-foreground">
                    {event.title} · {session.label}.{' '}
                    {selecting
                        ? 'Clique nos lugares (ou escolha uma fileira) e use as ações abaixo.'
                        : 'Clique para bloquear ou liberar um lugar.'}{' '}
                    Vendidos e reservados não podem ser alterados. {blocked}{' '}
                    bloqueado(s).
                </p>

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant={selecting ? 'primary' : 'secondary'}
                        onClick={() => {
                            setSelecting((v) => !v);
                            setSelected([]);
                        }}
                    >
                        {selecting ? 'Sair da seleção' : 'Selecionar vários'}
                    </Button>

                    {selecting && (
                        <>
                            <Select
                                aria-label="Fileira"
                                value={row}
                                onChange={(e) => setRow(e.target.value)}
                                className="w-32"
                            >
                                <option value="">Fileira…</option>
                                {rows.map((r) => (
                                    <option key={r} value={r}>
                                        Fileira {r}
                                    </option>
                                ))}
                            </Select>
                            <Button
                                size="sm"
                                variant="secondary"
                                disabled={row === ''}
                                onClick={selectRow}
                            >
                                Selecionar fileira
                            </Button>
                        </>
                    )}
                </div>

                {selecting && selected.length > 0 && (
                    <div className="mt-3 flex flex-wrap items-center gap-2 rounded-card border border-border bg-surface p-3">
                        <span className="font-body text-sm text-foreground">
                            {selected.length} selecionado(s):{' '}
                            <span className="text-faint">
                                {selectedSeats
                                    .slice(0, 12)
                                    .map((s) => s.code)
                                    .join(', ')}
                                {selected.length > 12 ? '…' : ''}
                            </span>
                        </span>
                        <span className="flex-1" />
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={busy}
                            onClick={() => bulk('block')}
                        >
                            Bloquear
                        </Button>
                        <Button
                            size="sm"
                            variant="secondary"
                            disabled={busy}
                            onClick={() => bulk('release')}
                        >
                            Liberar
                        </Button>
                        <Button
                            size="sm"
                            disabled={busy}
                            onClick={() => setIssuing(true)}
                        >
                            Emitir ingressos
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={busy}
                            onClick={() => setSelected([])}
                        >
                            Limpar
                        </Button>
                    </div>
                )}

                <div className="mt-6 max-w-4xl">
                    <SeatMap
                        seats={seats}
                        bounds={seatMap.bounds}
                        selectedIds={selected}
                        onToggle={toggle}
                        interactiveStatuses={EDITABLE}
                    />
                </div>
            </div>

            <IssueModal
                open={issuing}
                seats={selectedSeats}
                issueUrl={issueUrl}
                onClose={() => setIssuing(false)}
                onIssued={(st) => {
                    setStatuses((s) => ({ ...s, ...st }));
                    setSelected([]);
                    setIssuing(false);
                }}
            />
        </>
    );
}
