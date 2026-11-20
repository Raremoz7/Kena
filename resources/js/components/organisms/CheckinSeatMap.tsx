import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { cn } from '@/lib/utils';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';

export type CheckinSeatState = 'in' | 'pending' | 'free' | 'blocked';

export interface CheckinSeat {
    id: number;
    x: number;
    y: number;
    row: string;
    number: string;
    sector: string;
    state: CheckinSeatState;
    holder: string | null;
    code: string | null;
    ticketId: number | null;
}

interface MapResponse {
    seats: CheckinSeat[];
}

const SEAT = 20;
const PAD = 12;
const REFRESH_MS = 15000;

const LABEL: Record<CheckinSeatState, string> = {
    in: 'Já entrou',
    pending: 'Vendido, falta entrar',
    free: 'Livre',
    blocked: 'Bloqueado',
};

const FILL: Record<CheckinSeatState, string> = {
    in: 'fill-success',
    pending: 'fill-warning',
    free: 'fill-transparent stroke-seat-available',
    blocked: 'fill-border',
};

const SWATCH: Record<CheckinSeatState, string> = {
    in: 'bg-success',
    pending: 'bg-warning',
    free: 'border-[1.5px] border-seat-available',
    blocked: 'bg-border',
};

function AdmitButton({
    seat,
    busy,
    onAdmit,
}: {
    seat: CheckinSeat;
    busy: boolean;
    onAdmit: (s: CheckinSeat) => void;
}) {
    if (seat.state === 'in') {
        return (
            <span className="shrink-0 font-body text-xs font-medium text-success-text">
                Entrou ✓
            </span>
        );
    }

    if (seat.state !== 'pending' || !seat.ticketId) {
        return null;
    }

    return (
        <Button
            size="sm"
            className="shrink-0"
            disabled={busy}
            onClick={() => onAdmit(seat)}
        >
            {busy ? 'Confirmando...' : 'Dar entrada'}
        </Button>
    );
}

/**
 * Mapa de cadeiras da portaria: mostra quem já entrou, quem comprou e ainda
 * não chegou, e o que está livre. Atualiza sozinho enquanto está aberto.
 */
export function CheckinSeatMap({
    mapUrl,
    sessionId,
    refreshKey,
    admitUrl,
    onAdmitted,
}: {
    mapUrl: string;
    sessionId: number;
    /** Muda a cada entrada confirmada, para o mapa recarregar na hora. */
    refreshKey: number;
    admitUrl: string;
    onAdmitted: (progress: { checkedIn: number; total: number }) => void;
}) {
    const [seats, setSeats] = useState<CheckinSeat[] | null>(null);
    const [error, setError] = useState(false);
    const [pickedId, setPickedId] = useState<number | null>(null);
    const [filter, setFilter] = useState<CheckinSeatState | null>(null);
    const [admitting, setAdmitting] = useState<number | null>(null);
    // Quem entrou por aqui continua na lista "falta entrar" com "Entrou ✓",
    // para a lista não pular enquanto a portaria vai dando entrada em vários.
    const [justIn, setJustIn] = useState<number[]>([]);
    const picked = seats?.find((s) => s.id === pickedId) ?? null;

    /** Admite direto do mapa, sem recarregar: o assento vira verde na hora. */
    async function admit(seat: CheckinSeat) {
        if (!seat.ticketId || admitting !== null) {
            return;
        }

        setAdmitting(seat.id);

        try {
            const res = await api.post<{
                result: string;
                reason: string | null;
                progress: { checkedIn: number; total: number };
            }>(admitUrl, { session_id: sessionId, ticket_id: seat.ticketId });

            if (res.result === 'ok') {
                const done = (s: CheckinSeat): CheckinSeat =>
                    s.id === seat.id ? { ...s, state: 'in' } : s;
                setSeats((prev) => prev?.map(done) ?? prev);
                setJustIn((ids) => [...ids, seat.id]);
                navigator.vibrate?.(60);
            } else {
                veludoToast.error(
                    'Entrada não liberada',
                    res.reason ?? undefined,
                );
            }

            onAdmitted(res.progress);
        } catch (e) {
            veludoToast.error(
                'Erro',
                e instanceof ApiError
                    ? e.message
                    : 'Falha ao confirmar a entrada.',
            );
        } finally {
            setAdmitting(null);
        }
    }

    const load = useCallback(async () => {
        if (!sessionId) {
            return;
        }

        try {
            const res = await api.get<MapResponse>(
                `${mapUrl}?session_id=${sessionId}`,
            );
            setSeats(res.seats);
            setError(false);
        } catch {
            setError(true);
        }
    }, [mapUrl, sessionId]);

    useEffect(() => {
        setSeats(null);
        setPickedId(null);
        setJustIn([]);
    }, [sessionId]);

    useEffect(() => {
        void load();
        const timer = window.setInterval(() => void load(), REFRESH_MS);

        return () => window.clearInterval(timer);
    }, [load, refreshKey]);

    const counts = useMemo(() => {
        const c: Record<CheckinSeatState, number> = {
            in: 0,
            pending: 0,
            free: 0,
            blocked: 0,
        };
        seats?.forEach((s) => {
            c[s.state] += 1;
        });

        return c;
    }, [seats]);

    const box = useMemo(() => {
        if (!seats || seats.length === 0) {
            return null;
        }

        const xs = seats.map((s) => s.x);
        const ys = seats.map((s) => s.y);
        const minX = Math.min(...xs) - PAD;
        const minY = Math.min(...ys) - PAD;

        return {
            minX,
            minY,
            w: Math.max(...xs) - minX + SEAT + PAD,
            h: Math.max(...ys) - minY + SEAT + PAD,
        };
    }, [seats]);

    const pendingList = useMemo(
        () =>
            (seats ?? [])
                .filter((s) => s.state === 'pending' || justIn.includes(s.id))
                .sort(
                    (a, b) =>
                        a.row.localeCompare(b.row, 'pt-BR', {
                            numeric: true,
                        }) ||
                        a.number.localeCompare(b.number, 'pt-BR', {
                            numeric: true,
                        }),
                ),
        [seats, justIn],
    );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
                {(
                    ['in', 'pending', 'free', 'blocked'] as CheckinSeatState[]
                ).map((st) =>
                    st === 'blocked' && counts.blocked === 0 ? null : (
                        <button
                            key={st}
                            type="button"
                            onClick={() =>
                                setFilter((f) => (f === st ? null : st))
                            }
                            aria-pressed={filter === st}
                            className={cn(
                                'flex items-center gap-2 rounded-btn border px-3 py-1.5 font-body text-xs text-foreground',
                                filter === st
                                    ? 'border-foreground'
                                    : 'border-border',
                            )}
                        >
                            <span
                                className={cn(
                                    'inline-block size-3 rounded-[3px]',
                                    SWATCH[st],
                                )}
                            />
                            {LABEL[st]}
                            <span className="font-display">{counts[st]}</span>
                        </button>
                    ),
                )}
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => void load()}
                >
                    Atualizar
                </Button>
            </div>

            {error && (
                <p className="font-body text-xs text-danger-text">
                    Não foi possível carregar o mapa. Tentando de novo em
                    instantes.
                </p>
            )}

            {!seats && !error && (
                <p className="font-body text-xs text-faint">
                    Carregando mapa...
                </p>
            )}

            {seats && seats.length === 0 && (
                <p className="font-body text-xs text-faint">
                    Esta sessão não tem mapa de assentos.
                </p>
            )}

            {box && seats && (
                <div className="overflow-hidden rounded-btn border border-border bg-bg p-2">
                    <p className="mb-1 text-center font-body text-[10px] tracking-widest text-faint uppercase">
                        Palco
                    </p>
                    <svg
                        viewBox={`${box.minX} ${box.minY} ${box.w} ${box.h}`}
                        className="h-auto w-full"
                        role="img"
                        aria-label="Mapa de cadeiras"
                    >
                        {seats.map((s) => {
                            const dim = filter !== null && filter !== s.state;

                            return (
                                <rect
                                    key={s.id}
                                    x={s.x + 2}
                                    y={s.y + 2}
                                    width={SEAT - 4}
                                    height={SEAT - 4}
                                    rx={3}
                                    strokeWidth={s.state === 'free' ? 1.5 : 0}
                                    className={cn(
                                        FILL[s.state],
                                        'cursor-pointer',
                                        dim && 'opacity-15',
                                        picked?.id === s.id &&
                                            'stroke-foreground',
                                    )}
                                    style={
                                        picked?.id === s.id
                                            ? { strokeWidth: 2.5 }
                                            : undefined
                                    }
                                    onClick={() => setPickedId(s.id)}
                                >
                                    <title>{`Fila ${s.row}, ${s.number}: ${LABEL[s.state]}`}</title>
                                </rect>
                            );
                        })}
                    </svg>
                </div>
            )}

            {picked && (
                <div className="flex items-center justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-2 font-body text-sm">
                    <div className="min-w-0">
                        <p className="text-foreground">
                            {picked.sector} · Fila {picked.row}, assento{' '}
                            {picked.number}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {LABEL[picked.state]}
                            {picked.holder ? ` · ${picked.holder}` : ''}
                            {picked.code ? ` · ${picked.code}` : ''}
                        </p>
                    </div>
                    <AdmitButton
                        seat={picked}
                        busy={admitting === picked.id}
                        onAdmit={(x) => void admit(x)}
                    />
                </div>
            )}

            {filter === 'pending' && pendingList.length > 0 && (
                <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
                    {pendingList.map((s) => (
                        <li
                            key={s.id}
                            className="flex items-center justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-1.5 font-body text-xs"
                        >
                            <div className="min-w-0">
                                <p className="text-foreground">
                                    Fila {s.row}, {s.number}
                                </p>
                                <p className="truncate text-muted-foreground">
                                    {s.holder ?? 'Sem nome'}
                                </p>
                            </div>
                            <AdmitButton
                                seat={s}
                                busy={admitting === s.id}
                                onAdmit={(x) => void admit(x)}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
