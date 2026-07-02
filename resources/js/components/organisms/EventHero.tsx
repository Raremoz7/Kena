import { Badge } from '@/components/atoms/Badge';
import { Icon } from '@/components/atoms/Icon';
import { Tag } from '@/components/atoms/Tag';
import type { EventInfo } from '@/lib/veludo/types';

export function EventHero({ event }: { event: EventInfo }) {
    return (
        <section
            className="relative overflow-hidden border-b border-border"
            style={{ background: `linear-gradient(160deg, ${event.bannerFrom}, ${event.bannerTo})` }}
        >
            {event.bannerImage && (
                <img
                    src={event.bannerImage}
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full object-cover"
                />
            )}
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(100deg, oklch(0.14 0.012 48 / 0.94) 0%, oklch(0.14 0.012 48 / 0.7) 48%, oklch(0.14 0.012 48 / 0.35) 100%)',
                }}
            />
            <div className="relative mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
                <div className="flex flex-wrap items-center gap-3">
                    <Tag>{event.kicker}</Tag>
                    <Badge tone={event.status.tone}>{event.status.label}</Badge>
                </div>

                <h1 className="mt-4 max-w-3xl font-display text-[clamp(2.4rem,7vw,3.5rem)] leading-[0.96] font-bold text-foreground uppercase">
                    {event.title}
                </h1>

                <div className="mt-5 flex flex-wrap gap-x-5 gap-y-2">
                    <span className="flex items-center gap-1.5 font-body text-sm text-foreground/80">
                        <Icon name="calendar" size={16} />
                        {event.dateLabel} · {event.timeLabel}
                    </span>
                    <span className="flex items-center gap-1.5 font-body text-sm text-foreground/80">
                        <Icon name="map-pin" size={16} />
                        {event.venue.name}, {event.venue.city}
                    </span>
                    <span className="flex items-center gap-1.5 font-body text-sm text-foreground/80">
                        <Icon name="clock" size={16} />
                        {event.durationLabel}
                    </span>
                </div>
            </div>
        </section>
    );
}
