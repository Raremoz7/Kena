import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Badge  } from '@/components/atoms/Badge';
import type {BadgeProps} from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Price } from '@/components/atoms/Price';
import { Tag } from '@/components/atoms/Tag';
import type { EventStatus, VenueInfo } from '@/lib/veludo/types';

interface ListedEvent {
    id: number;
    slug: string;
    title: string;
    kicker: string;
    status: EventStatus;
    venue: VenueInfo;
    dateLabel: string;
    timeLabel: string;
    bannerFrom: string;
    bannerTo: string;
    bannerImage?: string;
    priceFrom: number;
}

function EventCard({ event }: { event: ListedEvent }) {
    const soldOut = event.status.tone === 'danger';

    return (
        <Link
            href={`/e/${event.slug}`}
            className="group block overflow-hidden rounded-card border border-border bg-surface transition-colors hover:border-[oklch(0.42_0.05_22)]"
        >
            <div
                className="relative h-40 overflow-hidden"
                style={{ background: `linear-gradient(160deg, ${event.bannerFrom}, ${event.bannerTo})` }}
            >
                {event.bannerImage && (
                    <img
                        src={event.bannerImage}
                        alt=""
                        aria-hidden="true"
                        className="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent" />
                <span className="absolute top-4 left-4">
                    <Badge tone={event.status.tone as BadgeProps['tone']}>{event.status.label}</Badge>
                </span>
            </div>
            <div className="p-5">
                <Tag>{event.kicker}</Tag>
                <h3 className="mt-1.5 font-display text-display-sm text-foreground uppercase">{event.title}</h3>
                <p className="mt-2 flex items-center gap-1.5 font-body text-sm text-muted-foreground">
                    <Icon name="calendar" size={15} />
                    {event.dateLabel} · {event.timeLabel}
                </p>
                <p className="flex items-center gap-1.5 font-body text-sm text-muted-foreground">
                    <Icon name="map-pin" size={15} />
                    {event.venue.name}
                </p>
                <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                    <span className="font-body text-xs text-faint">
                        {soldOut ? 'Esgotado' : 'a partir de'}
                    </span>
                    {!soldOut && <Price value={event.priceFrom} className="text-lg text-foreground" />}
                </div>
            </div>
        </Link>
    );
}

export default function EventsPage({ events, q = '' }: { events: ListedEvent[]; q?: string }) {
    const [term, setTerm] = useState(q);

    function search(e: FormEvent) {
        e.preventDefault();
        router.get('/eventos', term.trim() ? { q: term.trim() } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="Eventos" />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
                <Tag>Em cartaz</Tag>
                <h1 className="mt-2 font-display text-display-lg text-foreground uppercase">Eventos</h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Teatro, concertos e espetáculos com assento marcado.
                </p>

                <form onSubmit={search} className="mt-6 flex max-w-md gap-2">
                    <div className="relative flex-1">
                        <Icon
                            name="search"
                            size={16}
                            className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-faint"
                        />
                        <Input
                            value={term}
                            onChange={(e) => setTerm(e.target.value)}
                            placeholder="Buscar por evento ou cidade"
                            aria-label="Buscar eventos"
                            className="pl-9"
                        />
                    </div>
                    <Button type="submit" variant="secondary">
                        Buscar
                    </Button>
                </form>

                {events.length === 0 ? (
                    <div className="mt-10 rounded-card border border-border bg-surface px-6 py-12 text-center">
                        <p className="font-body text-sm text-muted-foreground">
                            {q ? `Nenhum evento para “${q}”.` : 'Nenhum evento em cartaz no momento.'}
                        </p>
                        {q && (
                            <Link href="/eventos" className="mt-2 inline-block font-body text-sm text-accent hover:underline">
                                Limpar busca
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {events.map((e) => (
                            <EventCard key={e.id} event={e} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
