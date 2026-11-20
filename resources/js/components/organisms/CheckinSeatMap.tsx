import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { cn } from '@/lib/utils';
import { api } from '@/lib/veludo/api';

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

/**
 * Mapa de cadeiras da portaria: mostra quem já entrou, quem comprou e ainda
 * não chegou, e o que está livre. Atualiza sozinho enquanto está aberto.
 */
export function CheckinSeatMap({
    mapUrl,
    sessionId,
    refreshKey,
}: {
    mapUrl: string;
    sessionId: number;
    /** Muda a cada entrada confirmada, para o mapa recarregar na hora. */
    refreshKey: number;
}) {
    const [seats, setSeats] = useState<CheckinSeat[] | null>(null);
    const [error, setError] = useState(false);
    const [picked, setPicked] = useState<CheckinSeat | null>(null);
    const [filter, setFilter] = useState<CheckinSeatState | null>(null);

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
        setPicked(null);
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
                .filter((s) => s.state === 'pending')
                .sort(
                    (a, b) =>
                        a.row.localeCompare(b.row, 'pt-BR', {
                            numeric: true,
                        }) ||
                        a.number.localeCompare(b.number, 'pt-BR', {
                            numeric: true,
                        }),
                ),
        [seats],
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
                                    onClick={() => setPicked(s)}
                                >
                                    <title>{`Fila ${s.row}, ${s.number}: ${LABEL[s.state]}`}</title>
                                </rect>
                            );
                        })}
                    </svg>
                </div>
            )}

            {picked && (
                <div className="rounded-btn border border-border bg-bg px-3 py-2 font-body text-sm">
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
            )}

            {filter === 'pending' && pendingList.length > 0 && (
                <ul className="flex max-h-72 flex-col gap-1 overflow-y-auto">
                    {pendingList.map((s) => (
                        <li
                            key={s.id}
                            className="flex justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-1.5 font-body text-xs"
                        >
                            <span className="text-foreground">
                                Fila {s.row}, {s.number}
                            </span>
                            <span className="truncate text-muted-foreground">
                                {s.holder ?? 'Sem nome'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
