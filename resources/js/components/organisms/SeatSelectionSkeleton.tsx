import { cn } from '@/lib/utils';

/**
 * Placeholder do mapa de assentos enquanto a prop `seatMap` (deferida) chega.
 * Espelha as dimensões de SeatSelection para a página não pular quando o mapa
 * real substitui este bloco.
 */
export function SeatSelectionSkeleton({ className }: { className?: string }) {
    return (
        <div className={className} aria-hidden="true">
            <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                <div className="min-w-0 overflow-hidden rounded-card border border-border bg-bg">
                    {/* barra de título do mapa */}
                    <div className="flex items-center justify-between border-b border-border px-4 py-2.5">
                        <span className="h-3 w-40 animate-pulse rounded-full bg-surface-2" />
                        <span className="h-3 w-10 animate-pulse rounded-full bg-surface-2" />
                    </div>

                    {/* área do mapa */}
                    <div className="flex h-[58vh] max-h-[560px] items-center justify-center lg:h-[600px] lg:max-h-none">
                        <div className="flex flex-col items-center gap-2">
                            {Array.from({ length: 7 }).map((_, row) => (
                                <div key={row} className="flex gap-2">
                                    {Array.from({ length: 12 }).map((_, seat) => (
                                        <span
                                            key={seat}
                                            className="size-4 animate-pulse rounded-[4px] bg-surface-2"
                                            style={{ animationDelay: `${(row * 12 + seat) * 12}ms` }}
                                        />
                                    ))}
                                </div>
                            ))}
                            <span className="mt-4 h-8 w-56 animate-pulse rounded-btn bg-surface-2" />
                        </div>
                    </div>

                    {/* legenda */}
                    <div className="flex flex-wrap justify-center gap-x-5 gap-y-2 border-t border-border px-4 py-4">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <span key={i} className="flex items-center gap-1.5">
                                <span className="size-3.5 animate-pulse rounded-[3px] bg-surface-2" />
                                <span className="h-2.5 w-16 animate-pulse rounded-full bg-surface-2" />
                            </span>
                        ))}
                    </div>
                </div>

                <aside
                    className={cn(
                        'hidden rounded-card border border-border bg-surface p-6',
                        'lg:sticky lg:top-20 lg:block lg:self-start',
                    )}
                >
                    <span className="block h-2.5 w-24 animate-pulse rounded-full bg-surface-2" />
                    <span className="mt-4 block h-4 w-full animate-pulse rounded-full bg-surface-2" />
                    <span className="mt-2 block h-4 w-3/4 animate-pulse rounded-full bg-surface-2" />
                    <span className="mt-6 block h-11 w-full animate-pulse rounded-btn bg-surface-2" />
                </aside>
            </div>
        </div>
    );
}
