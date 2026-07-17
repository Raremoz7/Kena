import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { SeatMap } from '@/components/organisms/SeatMap';
import { api, ApiError } from '@/lib/veludo/api';
import { veludoToast } from '@/lib/veludo/toast';
import type { Seat, SeatMapData, SeatStatus } from '@/lib/veludo/types';

interface SeatsAdminProps {
    event: { title: string; slug: string };
    session: { id: number; label: string };
    seatMap: SeatMapData;
    toggleUrl: string;
}

export default function AdminSeats({ event, session, seatMap, toggleUrl }: SeatsAdminProps) {
    const [statuses, setStatuses] = useState<Record<number, SeatStatus>>(() =>
        Object.fromEntries(seatMap.seats.map((s) => [s.id, s.status])),
    );

    const seats = useMemo(
        () => seatMap.seats.map((s) => ({ ...s, status: statuses[s.id] ?? s.status })),
        [seatMap.seats, statuses],
    );

    async function toggle(seat: Seat) {
        const current = statuses[seat.id] ?? seat.status;

        if (current !== 'available' && current !== 'blocked') {
            return;
        }

        try {
            const res = await api.post<{ id: number; status: SeatStatus }>(toggleUrl, {
                session_seat_id: seat.id,
            });
            setStatuses((s) => ({ ...s, [res.id]: res.status }));
        } catch (e) {
            const message = e instanceof ApiError ? e.message : 'Não foi possível alterar o assento.';
            veludoToast.error('Erro', message);
        }
    }

    const blocked = seats.filter((s) => s.status === 'blocked').length;

    return (
        <>
            <Head title={`Assentos · ${event.title}`} />
            <div className="px-4 py-6 sm:px-8 sm:py-8">
                <Link
                    href="/painel/pedidos"
                    className="inline-flex items-center gap-1.5 font-body text-sm text-muted-foreground hover:text-foreground"
                >
                    <Icon name="chevron-left" size={16} /> Painel
                </Link>
                <h1 className="mt-3 font-display text-display-lg text-foreground uppercase">Assentos</h1>
                <p className="mt-1 max-w-prose font-body text-sm text-muted-foreground">
                    {event.title} · {session.label} — clique para <strong>bloquear</strong> (cortesia, lugar
                    interditado) ou liberar. Vendidos e reservados não podem ser alterados. {blocked} bloqueado(s).
                </p>
                <div className="mt-6 max-w-4xl">
                    <SeatMap
                        seats={seats}
                        bounds={seatMap.bounds}
                        selectedIds={[]}
                        onToggle={toggle}
                        interactiveStatuses={['available', 'blocked']}
                    />
                </div>
            </div>
        </>
    );
}
