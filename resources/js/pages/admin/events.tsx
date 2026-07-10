import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';
import { AdminEventsTable  } from '@/components/organisms/AdminEventsTable';
import type {EventRow} from '@/components/organisms/AdminEventsTable';

export default function AdminEvents({ events }: { events: Paginator<EventRow> }) {
    return (
        <>
            <Head title="Painel — Eventos" />
            <div className="px-6 py-8 sm:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="font-display text-display-lg text-foreground uppercase">Eventos</h1>
                        <p className="mt-1 font-body text-sm text-muted-foreground">
                            Gerencie eventos, sessões e preços.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/eventos/novo">
                            <Icon name="plus" size={18} /> Novo evento
                        </Link>
                    </Button>
                </div>
                <div className="mt-6">
                    <AdminEventsTable events={events.data} editable />
                    <Pagination links={events.links} />
                </div>
            </div>
        </>
    );
}
