import { Icon } from '@/components/atoms/Icon';
import type { DriveState } from '@/components/organisms/photos/DriveImportSection';
import { cn } from '@/lib/utils';

/** Tempo restante a partir do ritmo médio até aqui. */
function remaining(
    done: number,
    total: number,
    elapsed: number,
): string | null {
    if (done < 2 || elapsed < 1 || done >= total) {
        return null;
    }

    const seconds = ((total - done) * elapsed) / done;

    if (seconds < 60) {
        return 'menos de 1 min';
    }

    const minutes = Math.round(seconds / 60);

    return minutes < 60
        ? `cerca de ${minutes} min`
        : `cerca de ${Math.floor(minutes / 60)} h ${minutes % 60} min`;
}

/**
 * Andamento da importação do Drive: na fila, lendo a pasta e, depois, uma
 * barra foto a foto com o tempo que falta. O painel atualiza a cada poucos
 * segundos enquanto a fila trabalha.
 */
export function ImportProgress({
    drive,
    className,
}: {
    drive: DriveState;
    className?: string;
}) {
    const p = drive.progress;
    const total = p?.total ?? null;
    const known = total !== null && total > 0;
    const percent = known
        ? Math.min(100, Math.round((p!.done / total) * 100))
        : 0;

    let title: string;

    if (drive.status === 'queued') {
        title = 'Na fila, começa em instantes…';
    } else if (!p || total === null) {
        title = 'Lendo a pasta do Drive…';
    } else if (total === 0) {
        title = 'A pasta do Drive não tem fotos.';
    } else {
        title = `${p.done} de ${total} ${total === 1 ? 'foto pronta' : 'fotos prontas'}`;
    }

    const eta = known && p ? remaining(p.done, total, p.elapsed) : null;

    return (
        <div
            role="status"
            aria-live="polite"
            className={cn(
                'flex flex-col gap-2.5 rounded-card border border-border bg-bg px-4 py-3',
                className,
            )}
        >
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <p className="flex items-center gap-2 font-body text-sm font-medium text-foreground">
                    <Icon
                        name="download"
                        size={16}
                        className="shrink-0 text-accent"
                    />
                    {title}
                </p>
                {known && (
                    <p className="font-body text-sm text-muted-foreground tabular-nums">
                        {percent}%{eta && ` · ${eta}`}
                    </p>
                )}
            </div>

            <div
                role="progressbar"
                aria-label="Andamento da importação"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={known ? percent : undefined}
                className="relative h-2 overflow-hidden rounded-full bg-surface-2"
            >
                {known ? (
                    <div
                        className="h-full rounded-full bg-accent transition-[width] duration-700 ease-out"
                        style={{ width: `${Math.max(percent, 2)}%` }}
                    />
                ) : (
                    <div className="absolute inset-y-0 w-1/3 animate-[import-slide_1.4s_ease-in-out_infinite] rounded-full bg-accent/70" />
                )}
            </div>

            {p && known && (
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 font-body text-xs text-muted-foreground">
                    <p className="tabular-nums">
                        {p.imported} {p.imported === 1 ? 'nova' : 'novas'}
                        {p.skipped > 0 && ` · ${p.skipped} já existiam`}
                        {p.failed > 0 && (
                            <span className="text-danger-text">
                                {' '}
                                · {p.failed}{' '}
                                {p.failed === 1 ? 'falhou' : 'falharam'}
                            </span>
                        )}
                    </p>
                    {p.current && p.done < total && (
                        <p
                            className="max-w-full truncate font-mono text-[11px] text-faint sm:max-w-80"
                            title={p.current}
                        >
                            {p.current}
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}
