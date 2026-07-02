import { Head, Link } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import { SeatSelection } from '@/components/organisms/SeatSelection';
import type { SeatMapData } from '@/lib/veludo/types';

interface SeatsPageProps {
    event: { slug: string; title: string };
    session: { id: number; label: string };
    seatMap: SeatMapData;
    reserveUrl: string;
    availabilityUrl: string;
}

export default function SeatsPage({ event, session, seatMap, reserveUrl, availabilityUrl }: SeatsPageProps) {
    return (
        <>
            <Head title={`Assentos · ${event.title}`} />

            <div className="border-b border-border bg-surface/60">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <nav className="flex min-w-0 items-center gap-1.5 font-body text-sm text-muted-foreground">
                        <Link href={`/e/${event.slug}`} className="truncate hover:text-foreground">
                            {event.title}
                        </Link>
                        <Icon name="chevron-right" size={14} className="text-faint" />
                        <span className="text-foreground">Assentos</span>
                    </nav>
                    <span className="font-body text-xs text-faint">{session.label}</span>
                </div>
            </div>

            <SeatSelection
                seatMap={seatMap}
                reserveUrl={reserveUrl}
                availabilityUrl={availabilityUrl}
                className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8"
            />
        </>
    );
}
