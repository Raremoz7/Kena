import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';
import { EventHero } from '@/components/organisms/EventHero';
import { SeatSelection } from '@/components/organisms/SeatSelection';
import { SessionList } from '@/components/organisms/SessionList';
import type { EventInfo, SeatMapData, Sector } from '@/lib/veludo/types';

interface SessionOption {
    id: number;
    label: string;
    dateLabel: string;
    timeLabel: string;
    availableCount: number;
    seatsUrl: string;
}

function InfoCard({ icon, label, value }: { icon: IconName; label: string; value: string }) {
    return (
        <div className="rounded-[10px] border border-border bg-surface p-4">
            <div className="flex items-center gap-2 text-faint">
                <Icon name={icon} size={16} />
                <span className="kicker">{label}</span>
            </div>
            <p className="mt-2 font-body text-sm font-medium text-foreground">{value}</p>
        </div>
    );
}

interface EventPageProps {
    event: EventInfo;
    sectors: Sector[];
    sessionId: number;
    sessionLabel: string;
    sessions: SessionOption[];
    seatMap: SeatMapData;
    reserveUrl: string;
}

export default function EventPage({
    event,
    sectors,
    sessionId,
    sessionLabel,
    sessions,
    seatMap,
    reserveUrl,
}: EventPageProps) {
    const multi = sessions.length > 1;

    return (
        <>
            <Head title={event.title} />
            <EventHero event={event} />

            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
                <div className="grid gap-10 lg:grid-cols-[1fr_320px]">
                    <div>
                        <h2 className="kicker text-faint">Sobre o espetáculo</h2>
                        <p className="mt-3 max-w-prose font-body text-[15px] leading-relaxed text-muted-foreground">
                            {event.description}
                        </p>

                        <div className="mt-8 grid gap-3 sm:grid-cols-2">
                            <InfoCard
                                icon="calendar"
                                label="Data"
                                value={`${event.dateLabel} · ${event.timeLabel}`}
                            />
                            <InfoCard icon="clock" label="Duração" value={event.durationLabel} />
                        </div>
                    </div>

                    <aside className="lg:sticky lg:top-20 lg:self-start">
                        <SessionList
                            eventSlug={event.slug}
                            sessionId={sessionId}
                            sessionLabel={sessionLabel}
                            sectors={sectors}
                        />
                    </aside>
                </div>

                <section id="assentos" className="mt-12 scroll-mt-20">
                    {multi ? (
                        <>
                            <h2 className="kicker text-faint">Escolha a sessão</h2>
                            <p className="mt-2 max-w-prose font-body text-sm text-muted-foreground">
                                {event.venue.name}, {event.venue.city}
                            </p>
                            <ul className="mt-6 flex flex-col gap-3">
                                {sessions.map((s) => {
                                    const soldOut = s.availableCount === 0;

                                    return (
                                        <li
                                            key={s.id}
                                            className="flex items-center justify-between gap-4 rounded-card border border-border bg-surface p-4"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Icon name="calendar" size={18} className="text-accent" />
                                                <div>
                                                    <p className="font-body text-sm font-medium text-foreground">
                                                        {s.dateLabel} · {s.timeLabel}
                                                    </p>
                                                    <p className="font-body text-xs text-faint">
                                                        {soldOut
                                                            ? 'Esgotado'
                                                            : `${s.availableCount} lugares disponíveis`}
                                                    </p>
                                                </div>
                                            </div>
                                            {soldOut ? (
                                                <span className="font-body text-xs text-faint">Esgotado</span>
                                            ) : (
                                                <Button asChild size="sm">
                                                    <Link href={s.seatsUrl}>
                                                        Escolher assentos
                                                        <Icon name="arrow-right" size={16} />
                                                    </Link>
                                                </Button>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    ) : (
                        <>
                            <h2 className="kicker text-faint">Escolha seus assentos</h2>
                            <p className="mt-2 max-w-prose font-body text-sm text-muted-foreground">
                                {sessionLabel} · {event.venue.name}, {event.venue.city}
                            </p>
                            <SeatSelection seatMap={seatMap} reserveUrl={reserveUrl} className="mt-6" />
                        </>
                    )}
                </section>
            </div>
        </>
    );
}
