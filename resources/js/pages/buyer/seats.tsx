import { Head, Link } from '@inertiajs/react';
import { Icon } from '@/components/atoms/Icon';
import { LotBanner } from '@/components/molecules/LotBanner';
import type { LotInfo } from '@/components/molecules/LotBanner';
import { SeatSelection } from '@/components/organisms/SeatSelection';
import type { SeatMapData } from '@/lib/veludo/types';

interface SeatsPageProps {
    event: { slug: string; title: string };
    session: { id: number; label: string };
    seatMap: SeatMapData;
    reserveUrl: string;
    availabilityUrl: string;
    maxSeats: number;
    emailCodeUrl: string;
    lot: LotInfo | null;
}

export default function SeatsPage({ event, session, seatMap, reserveUrl, availabilityUrl, maxSeats, emailCodeUrl, lot }: SeatsPageProps) {
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

            {lot && (
                <div className="mx-auto max-w-6xl px-4 pt-6 sm:px-6">
                    <LotBanner lot={lot} />
                </div>
            )}

            <SeatSelection
                seatMap={seatMap}
                reserveUrl={reserveUrl}
                availabilityUrl={availabilityUrl}
                maxSeats={maxSeats}
                emailCodeUrl={emailCodeUrl}
                className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8"
            />
        </>
    );
}
