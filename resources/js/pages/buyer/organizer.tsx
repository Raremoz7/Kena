import { Head } from '@inertiajs/react';
import { EventCard } from '@/pages/buyer/events';
import type { ListedEvent } from '@/pages/buyer/events';

interface OrganizerPageProps {
    organization: { name: string; slug: string };
    events: ListedEvent[];
}

/** Página pública do organizador: os eventos à venda dele. */
export default function OrganizerPage({
    organization,
    events,
}: OrganizerPageProps) {
    return (
        <>
            <Head title={organization.name} />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-12">
                <p className="kicker text-faint">Organizador</p>
                <h1 className="mt-2 font-display text-display-lg text-foreground uppercase">
                    {organization.name}
                </h1>

                {events.length === 0 ? (
                    <p className="mt-6 font-body text-sm text-muted-foreground">
                        Nenhum evento à venda agora. Volte em breve.
                    </p>
                ) : (
                    <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
