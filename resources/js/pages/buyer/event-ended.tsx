import { Head, Link } from "@inertiajs/react";
import { Button } from "@/components/atoms/Button";
import { Icon } from "@/components/atoms/Icon";
import { EventHero } from "@/components/organisms/EventHero";
import type { EventInfo } from "@/lib/veludo/types";

interface EventEndedProps {
    event: EventInfo;
    /** Galeria de fotos publicada do evento, quando existe. */
    photosUrl: string | null;
}

export default function EventEndedPage({ event, photosUrl }: EventEndedProps) {
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
                    </div>

                    <aside className="lg:sticky lg:top-20 lg:self-start">
                        <div className="rounded-card border border-border bg-surface p-5">
                            <p className="font-body text-sm font-medium text-foreground">Este evento já aconteceu</p>
                            <p className="mt-1 font-body text-sm text-muted-foreground">
                                As vendas foram encerradas. Veja o que está em cartaz agora.
                            </p>
                            <div className="mt-4 flex flex-col gap-2">
                                <Button asChild>
                                    <Link href="/eventos">
                                        Ver próximos eventos
                                        <Icon name="arrow-right" size={16} />
                                    </Link>
                                </Button>
                                {photosUrl && (
                                    <Button asChild variant="secondary">
                                        <Link href={photosUrl}>Ver fotos do evento</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
