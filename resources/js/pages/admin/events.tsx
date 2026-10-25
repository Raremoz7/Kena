import { Head } from '@inertiajs/react';
import { AdminPage, PageHeader } from '@/components/molecules/PageHeader';
import { Pagination } from '@/components/molecules/Pagination';
import type { Paginator } from '@/components/molecules/Pagination';
import { AdminEventsTable } from '@/components/organisms/AdminEventsTable';
import type { EventRow } from '@/components/organisms/AdminEventsTable';

export default function AdminEvents({
    events,
}: {
    events: Paginator<EventRow>;
}) {
    return (
        <>
            <Head title="Painel — Eventos" />
            <AdminPage>
                <PageHeader
                    title="Eventos"
                    description="Gerencie eventos, sessões e preços."
                    primary={{
                        label: 'Novo evento',
                        href: '/painel/eventos/novo',
                    }}
                />
                <AdminEventsTable events={events.data} editable />
                <Pagination links={events.links} />
            </AdminPage>
        </>
    );
}
