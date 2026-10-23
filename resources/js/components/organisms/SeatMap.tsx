import { useId, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, ReactElement } from 'react';
import { TransformComponent, TransformWrapper } from 'react-zoom-pan-pinch';
import type { ReactZoomPanPinchRef } from 'react-zoom-pan-pinch';
import { Icon } from '@/components/atoms/Icon';
import { SeatTooltipContent } from '@/components/molecules/SeatTooltip';
import { cn } from '@/lib/utils';
import type { Seat, SeatBounds, SeatStatus } from '@/lib/veludo/types';

const SEAT = 20; // tamanho do assento em px (coords do mapa real)
const PAD = 26;
const GUTTER = 22; // espaço p/ rótulo de fileira
const STAGE_H = 40;

// Acima desta escala (assento ≈ 14px) os assentos ficam tocáveis; abaixo, o
// toque/arraste só navega o mapa (evita seleção acidental num alvo minúsculo).
const MIN_TAP_SCALE = 0.7;

const NAV_KEYS = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];

/**
 * Próximo lugar clicável na direção da tecla. Esquerda/direita andam na fileira;
 * cima/baixo vão para a fileira vizinha, no lugar mais próximo na horizontal;
 * Home/End vão para a ponta da fileira. Sem vizinho, fica onde está.
 */
function neighborSeat(rows: Seat[][], from: Seat, key: string): Seat | null {
    const r = rows.findIndex((row) => row.some((s) => s.id === from.id));

    if (r < 0) {
        return null;
    }

    const row = rows[r];
    const i = row.findIndex((s) => s.id === from.id);

    switch (key) {
        case 'ArrowLeft':
            return row[i - 1] ?? null;
        case 'ArrowRight':
            return row[i + 1] ?? null;
        case 'Home':
            return row[0];
        case 'End':
            return row[row.length - 1];
        default: {
            const target = rows[key === 'ArrowUp' ? r - 1 : r + 1];

            if (!target) {
                return null;
            }

            return target.reduce((best, s) => (Math.abs(s.x - from.x) < Math.abs(best.x - from.x) ? s : best));
        }
    }
}
const MAX_SCALE = 2;

const HACHURA =
    'repeating-linear-gradient(45deg, var(--seat-hatch-a), var(--seat-hatch-a) 2px, var(--seat-hatch-b) 2px, var(--seat-hatch-b) 4px)';

type Visual = 'available' | 'selected' | 'held' | 'sold' | 'blocked';

function SeatDot({
    seat,
    selected,
    onToggle,
    onHover,
    onLeave,
    left,
    top,
    interactiveStatuses,
    tabbable,
    buttonRef,
}: {
    seat: Seat;
    selected: boolean;
    onToggle: (s: Seat) => void;
    onHover: (s: Seat, el: HTMLButtonElement) => void;
    onLeave: () => void;
    left: number;
    top: number;
    interactiveStatuses: SeatStatus[];
    /** Tabindex móvel: só um lugar do mapa fica no Tab; as setas andam entre eles. */
    tabbable: boolean;
    buttonRef: (el: HTMLButtonElement | null) => void;
}) {
    const visual: Visual = selected ? 'selected' : (seat.status as Visual);
    const interactive = interactiveStatuses.includes(seat.status);
    const accessible = seat.kind === 'accessible';

    const cls = cn(
        'absolute flex items-center justify-center rounded-[4px] transition-transform duration-100 outline-none',
        {
            available: accessible
                ? 'border-[1.5px] border-seat-pcd bg-transparent hover:z-10 hover:scale-[1.5] hover:shadow-[0_0_0_3px_oklch(0.52_0.14_230/0.3)] focus-visible:z-10 focus-visible:scale-[1.5] focus-visible:ring-2 focus-visible:ring-seat-pcd cursor-pointer'
                : 'border-[1.5px] border-seat-available bg-transparent hover:z-10 hover:scale-[1.5] hover:shadow-[0_0_0_3px_oklch(0.52_0.14_155/0.3)] focus-visible:z-10 focus-visible:scale-[1.5] focus-visible:ring-2 focus-visible:ring-seat-available cursor-pointer',
            selected:
                'bg-seat-selected text-white hover:z-10 hover:scale-[1.5] focus-visible:z-10 focus-visible:scale-[1.5] focus-visible:ring-2 focus-visible:ring-foreground cursor-pointer',
            held: 'border-[1.5px] border-dashed border-seat-held cursor-not-allowed',
            sold: 'bg-seat-sold cursor-not-allowed',
            blocked: 'cursor-not-allowed',
        }[visual],
        interactive && 'cursor-pointer hover:z-10 hover:scale-[1.4]',
    );

    const statusLabel = { available: 'Disponível', selected: 'Selecionado', held: 'Em reserva', sold: 'Ocupado', blocked: 'Bloqueado' }[
        visual
    ];

    return (
        // Assentos vendidos/bloqueados são botões inativos (disabled). A WCAG isenta
        // componentes inativos do contraste, mas "este lugar está ocupado" é informação
        // de que o comprador precisa: o ocupado tem 3:1 sobre o fundo e o número 4.5:1+.
        <button
            ref={buttonRef}
            type="button"
            tabIndex={tabbable ? 0 : -1}
            disabled={!interactive}
            aria-pressed={interactive ? selected : undefined}
            aria-label={`Fila ${seat.row}, assento ${seat.number}. ${statusLabel}${seat.kind === 'accessible' ? ', cadeirante' : seat.kind === 'companion' ? ', acompanhante' : ''}.`}
            onClick={() => interactive && onToggle(seat)}
            onMouseEnter={(e) => onHover(seat, e.currentTarget)}
            onMouseLeave={onLeave}
            onFocus={(e) => onHover(seat, e.currentTarget)}
            onBlur={onLeave}
            className={cls}
            style={{ left, top, width: SEAT, height: SEAT, background: visual === 'blocked' ? HACHURA : undefined }}
        >
            <span
                className={cn('font-body text-[9px] leading-none font-semibold tabular', {
                    available: accessible ? 'text-seat-pcd-text' : 'text-seat-available-text',
                    selected: 'text-white',
                    held: 'text-seat-held-text',
                    sold: 'text-seat-sold-text',
                    blocked: 'text-seat-blocked-text',
                }[visual])}
            >
                {seat.number}
            </span>
            {(seat.kind === 'accessible' || seat.kind === 'companion') && (
                <span className="absolute -top-0.5 -right-0.5 size-[7px] rounded-full border border-bg bg-seat-pcd" />
            )}
        </button>
    );
}

const legend: { label: string; render: ReactElement }[] = [
    { label: 'Disponível', render: <span className="size-3.5 rounded-[3px] border-[1.5px] border-seat-available" /> },
    { label: 'Selecionado', render: <span className="size-3.5 rounded-[3px] bg-seat-selected" /> },
    { label: 'Ocupado', render: <span className="size-3.5 rounded-[3px] bg-seat-sold" /> },
    {
        label: 'Cadeirante',
        render: (
            <span className="flex size-3.5 items-center justify-center rounded-[3px] border-[1.5px] border-seat-pcd">
                <Icon name="wheelchair" size={9} className="text-seat-pcd" />
            </span>
        ),
    },
    {
        label: 'Acompanhante',
        render: (
            <span className="flex size-3.5 items-center justify-center">
                <span className="size-2 rounded-full bg-seat-pcd" />
            </span>
        ),
    },
];

function ZoomButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactElement }) {
    return (
        <button
            type="button"
            aria-label={label}
            onClick={onClick}
            className="flex size-7 items-center justify-center rounded-[9px] border border-border bg-surface-2/90 text-foreground/90 shadow-[0_2px_8px_rgba(0,0,0,0.18)] backdrop-blur-sm transition-colors hover:bg-surface-2"
        >
            {children}
        </button>
    );
}

interface SeatMapProps {
    seats: Seat[];
    bounds: SeatBounds;
    selectedIds: number[];
    onToggle: (seat: Seat) => void;
    className?: string;
    /** Status clicáveis (default só 'available'). Admin usa ['available','blocked']. */
    interactiveStatuses?: SeatStatus[];
}

export function SeatMap({
    seats,
    bounds,
    selectedIds,
    onToggle,
    className,
    interactiveStatuses = ['available'],
}: SeatMapProps) {
    const viewportRef = useRef<HTMLDivElement>(null);
    const wrapperRef = useRef<ReactZoomPanPinchRef | null>(null);
    const [hover, setHover] = useState<{ seat: Seat; x: number; y: number } | null>(null);
    const [scale, setScale] = useState(MIN_TAP_SCALE);
    const [hasInteracted, setHasInteracted] = useState(false);

    // escalas de fit/initial calculadas a partir da largura do viewport
    const [fit, setFit] = useState<{ min: number; initial: number; key: number } | null>(null);

    const selected = useMemo(() => new Set(selectedIds), [selectedIds]);

    // Teclado: só os lugares clicáveis, agrupados por fileira (de cima para baixo)
    // e ordenados na fileira (da esquerda para a direita).
    const buttons = useRef(new Map<number, HTMLButtonElement>());
    const keysHintId = useId();
    const navRows = useMemo(() => {
        const byRow = new Map<string, Seat[]>();

        for (const s of seats) {
            if (interactiveStatuses.includes(s.status)) {
                byRow.set(s.row, [...(byRow.get(s.row) ?? []), s]);
            }
        }

        return [...byRow.values()]
            .map((row) => row.sort((a, b) => a.x - b.x))
            .sort((a, b) => a[0].y - b[0].y);
    }, [seats, interactiveStatuses]);
    const [activeId, setActiveId] = useState<number | null>(null);
    const navSeats = navRows.flat();
    // O lugar no Tab: o escolhido pelas setas, senão o 1º selecionado, senão o 1º livre.
    const tabSeatId =
        (navSeats.some((s) => s.id === activeId) ? activeId : null) ??
        navSeats.find((s) => selected.has(s.id))?.id ??
        navSeats[0]?.id ??
        null;

    function onMapKeyDown(e: KeyboardEvent<HTMLDivElement>) {
        if (!NAV_KEYS.includes(e.key)) {
            return;
        }

        const from = navSeats.find((s) => s.id === tabSeatId);
        const next = from ? neighborSeat(navRows, from, e.key) : null;
        e.preventDefault();

        if (!next) {
            return;
        }

        setActiveId(next.id);
        const el = buttons.current.get(next.id);
        el?.focus({ preventScroll: true });

        // Lugar fora da área visível do mapa: leva o mapa até ele.
        const view = viewportRef.current?.getBoundingClientRect();
        const box = el?.getBoundingClientRect();

        if (el && view && box && (box.left < view.left || box.right > view.right || box.top < view.top || box.bottom > view.bottom)) {
            wrapperRef.current?.zoomToElement(el, Math.max(scale, MIN_TAP_SCALE), 150);
        }
    }

    const contentW = bounds.maxX - bounds.minX + SEAT;
    const contentH = bounds.maxY - bounds.minY + SEAT;
    const boardW = GUTTER + PAD + contentW + PAD;
    const boardH = PAD + contentH + 14 + STAGE_H + PAD;

    // rótulos de fileira (um por fileira padrão), à esquerda
    const rowLabels = useMemo(() => {
        const byRow = new Map<string, number>();

        for (const s of seats) {
            if (s.row.length === 1 && !byRow.has(s.row)) {
                byRow.set(s.row, s.y);
            }
        }

        return [...byRow.entries()].map(([row, y]) => ({ row, top: PAD + (y - bounds.minY) }));
    }, [seats, bounds]);

    // mede o viewport e define minScale (cabe inteiro) e initialScale
    // (mobile abre legível/parcial; desktop abre com o mapa inteiro à vista).
    useLayoutEffect(() => {
        const el = viewportRef.current;

        if (!el) {
            return;
        }

        function measure() {
            const w = el!.clientWidth;
            const fitScale = Math.min(MAX_SCALE, +(w / boardW).toFixed(3)) || MIN_TAP_SCALE;
            const min = Math.min(fitScale, MIN_TAP_SCALE);
            const isNarrow = w < 1024;
            const initial = isNarrow
                ? Math.min(MAX_SCALE, Math.max(MIN_TAP_SCALE, fitScale))
                : fitScale;

            setScale(initial);
            setFit((prev) => ({ min, initial, key: (prev?.key ?? 0) + 1 }));
        }

        measure();

        const ro = new ResizeObserver(measure);
        ro.observe(el);

        return () => ro.disconnect();
    }, [boardW]);

    const tappable = scale >= MIN_TAP_SCALE - 0.001;

    return (
        <div className={cn('min-w-0 overflow-hidden rounded-card border border-border bg-bg', className)}>
            <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                <span className="kicker text-faint">Plateia · Teatro UNIP</span>
                <span className="font-body text-[11px] tabular text-muted-foreground">{Math.round(scale * 100)}%</span>
            </div>

            <div
                ref={viewportRef}
                className="relative h-[58vh] max-h-[560px] overflow-hidden lg:h-[600px] lg:max-h-none"
            >
                {fit && (
                    <TransformWrapper
                        key={fit.key}
                        ref={wrapperRef}
                        minScale={fit.min}
                        maxScale={MAX_SCALE}
                        initialScale={fit.initial}
                        centerOnInit
                        limitToBounds
                        doubleClick={{ mode: 'zoomIn', step: 0.7 }}
                        wheel={{ step: 0.12, activationKeys: ['Control'] }}
                        pinch={{ step: 5 }}
                        panning={{ velocityDisabled: false }}
                        onTransform={(_, state) => setScale(state.scale)}
                        onPanningStart={() => setHasInteracted(true)}
                        onZoomStart={() => setHasInteracted(true)}
                    >
                        <TransformComponent wrapperClass="!h-full !w-full">
                            <div
                                role="group"
                                aria-label="Mapa de assentos"
                                aria-describedby={keysHintId}
                                onKeyDown={onMapKeyDown}
                                className="relative"
                                style={{ width: boardW, height: boardH, pointerEvents: tappable ? 'auto' : 'none' }}
                            >
                                {/* rótulos de fileira */}
                                {rowLabels.map(({ row, top }) => (
                                    <span
                                        key={row}
                                        className="absolute font-body text-[10px] font-semibold text-faint"
                                        style={{ left: 2, top: top - 6 }}
                                    >
                                        {row}
                                    </span>
                                ))}

                                {/* assentos */}
                                {seats.map((s) => (
                                    <SeatDot
                                        key={s.id}
                                        seat={s}
                                        selected={selected.has(s.id)}
                                        onToggle={onToggle}
                                        onHover={(seat, el) => {
                                            const r = el.getBoundingClientRect();
                                            setHover({ seat, x: r.left + r.width / 2, y: r.top });
                                        }}
                                        onLeave={() => setHover(null)}
                                        left={GUTTER + PAD + (s.x - bounds.minX) - SEAT / 2}
                                        top={PAD + (s.y - bounds.minY) - SEAT / 2}
                                        interactiveStatuses={interactiveStatuses}
                                        tabbable={s.id === tabSeatId}
                                        buttonRef={(el) => {
                                            if (el) {
                                                buttons.current.set(s.id, el);
                                            } else {
                                                buttons.current.delete(s.id);
                                            }
                                        }}
                                    />
                                ))}

                                {/* palco */}
                                <div
                                    className="absolute flex items-center justify-center rounded-btn border border-border bg-surface-2"
                                    style={{ left: GUTTER + PAD, top: PAD + contentH + 14, width: contentW, height: STAGE_H }}
                                >
                                    <span className="kicker text-muted-foreground">Palco</span>
                                </div>
                            </div>
                        </TransformComponent>
                    </TransformWrapper>
                )}

                <p id={keysHintId} className="sr-only">
                    Use as setas para andar entre os lugares livres e Enter ou Espaço para escolher.
                </p>

                {/* sombra interna: sinaliza que há mais mapa fora da viewport */}
                <div className="pointer-events-none absolute inset-0 shadow-[inset_0_0_26px_6px_var(--bg)]" />

                {/* controles de apoio (menores) — ancorados à viewport fixa */}
                <div className="absolute top-2.5 right-2.5 flex flex-col gap-1.5">
                    <ZoomButton label="Aproximar" onClick={() => wrapperRef.current?.zoomIn()}>
                        <Icon name="plus" size={14} />
                    </ZoomButton>
                    <ZoomButton label="Afastar" onClick={() => wrapperRef.current?.zoomOut()}>
                        <Icon name="minus" size={14} />
                    </ZoomButton>
                    <ZoomButton label="Ajustar à tela" onClick={() => wrapperRef.current?.resetTransform()}>
                        <Icon name="maximize" size={13} />
                    </ZoomButton>
                </div>

                {/* dica de gesto — some após a primeira interação */}
                {!hasInteracted && (
                    <div className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full border border-border bg-surface-2/90 px-3 py-1.5 backdrop-blur-sm">
                        <span className="font-body text-[10px] text-muted-foreground">
                            Pinça aproxima e afasta · arraste para mover
                        </span>
                    </div>
                )}
            </div>

            {/* tooltip flutuante único */}
            {hover && (
                <div
                    className="pointer-events-none fixed z-50 -translate-x-1/2 -translate-y-full rounded-card border border-border bg-surface-2 p-4 shadow-2xl"
                    style={{ left: hover.x, top: hover.y - 10 }}
                >
                    <SeatTooltipContent seat={hover.seat} />
                </div>
            )}

            <div className="flex flex-wrap justify-center gap-x-5 gap-y-2 border-t border-border px-4 py-4">
                {legend.map((item) => (
                    <span key={item.label} className="flex items-center gap-1.5">
                        {item.render}
                        <span className="font-body text-[11px] text-muted-foreground">{item.label}</span>
                    </span>
                ))}
            </div>
        </div>
    );
}
