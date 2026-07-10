import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Price } from '@/components/atoms/Price';
import { Spinner } from '@/components/atoms/Spinner';
import { FormField } from '@/components/molecules/FormField';
import { Modal } from '@/components/molecules/Modal';
import { SeatSheet } from '@/components/molecules/SeatSheet';
import { SeatMap } from '@/components/organisms/SeatMap';
import { veludoToast } from '@/lib/veludo/toast';
import type { Seat, SeatMapData, SeatStatus } from '@/lib/veludo/types';

function SelectionPanel({
    seats,
    total,
    onRemove,
    onClear,
    onCheckout,
    submitting,
}: {
    seats: Seat[];
    total: number;
    onRemove: (seat: Seat) => void;
    onClear: () => void;
    onCheckout: () => void;
    submitting: boolean;
}) {
    return (
        <div className="flex h-full flex-col">
            <p className="kicker text-faint">Sua seleção</p>

            {seats.length === 0 ? (
                <p className="mt-3 font-body text-sm text-muted-foreground">
                    Toque nos assentos verdes do mapa para escolher seus lugares.
                </p>
            ) : (
                <ul className="mt-3 flex flex-col gap-2">
                    {seats.map((s) => (
                        <li
                            key={s.id}
                            className="flex items-center justify-between gap-3 rounded-btn border border-border bg-bg px-3 py-2"
                        >
                            <p className="min-w-0 font-body text-sm">
                                <span className="font-display font-semibold text-foreground">{s.code}</span>{' '}
                                <span className="text-muted-foreground">· {s.sectorName}</span>
                            </p>
                            <div className="flex items-center gap-2">
                                <Price value={s.price} className="text-sm text-muted-foreground" />
                                <button
                                    type="button"
                                    aria-label={`Remover ${s.code}`}
                                    onClick={() => onRemove(s)}
                                    className="text-faint transition-colors hover:text-danger-text"
                                >
                                    <Icon name="close" size={15} />
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-5 flex items-center justify-between border-t border-border pt-4">
                <span className="font-body text-sm text-muted-foreground">Subtotal</span>
                <Price value={total} className="text-lg text-foreground" />
            </div>

            <div className="mt-4 flex flex-col gap-2">
                <Button
                    variant="success"
                    block
                    disabled={seats.length === 0 || submitting}
                    onClick={onCheckout}
                >
                    {submitting ? (
                        <Spinner />
                    ) : (
                        <>
                            Ir para o checkout
                            <Icon name="arrow-right" size={18} />
                        </>
                    )}
                </Button>
                {seats.length > 0 && (
                    <Button variant="ghost" block size="sm" onClick={onClear} disabled={submitting}>
                        Limpar seleção
                    </Button>
                )}
            </div>
        </div>
    );
}

interface SeatSelectionProps {
    seatMap: SeatMapData;
    className?: string;
    /** Rota POST que cria o hold a partir da seleção. */
    reserveUrl: string;
    /** Rota GET de disponibilidade (polling). Opcional. */
    availabilityUrl?: string;
}

/**
 * Seleção de assentos (mapa + painel + barra mobile). Reserva via POST Inertia e
 * mantém o mapa vivo por polling da disponibilidade.
 */
export function SeatSelection({ seatMap, className, reserveUrl, availabilityUrl }: SeatSelectionProps) {
    const [statuses, setStatuses] = useState<Record<number, SeatStatus>>(() =>
        Object.fromEntries(seatMap.seats.map((s) => [s.id, s.status])),
    );
    const [selected, setSelected] = useState<Seat[]>([]);
    const [submitting, setSubmitting] = useState(false);

    const page = usePage<{ auth: { user: { id: number } | null }; ziggy?: { location?: string } }>();
    const user = page.props.auth?.user ?? null;
    const currentUrl = page.props.ziggy?.location ?? page.url;
    const [guestOpen, setGuestOpen] = useState(false);
    const [guest, setGuest] = useState({ name: '', email: '', cpf: '' });
    const [guestErrors, setGuestErrors] = useState<Record<string, string>>({});

    const seats = useMemo(
        () => seatMap.seats.map((s) => ({ ...s, status: statuses[s.id] ?? s.status })),
        [seatMap.seats, statuses],
    );
    const selectedIds = useMemo(() => selected.map((s) => s.id), [selected]);
    const total = selected.reduce((sum, s) => sum + s.price, 0);

    // Polling da disponibilidade.
    useEffect(() => {
        if (!availabilityUrl) {
            return;
        }

        let active = true;
        async function poll() {
            try {
                const res = await fetch(availabilityUrl as string, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok || !active) {
                    return;
                }

                const data = (await res.json()) as { seats: Record<number, SeatStatus> };
                setStatuses(data.seats);
            } catch {
                /* silencioso — tenta de novo no próximo tick */
            }
        }
        const id = window.setInterval(poll, 4000);

        return () => {
            active = false;
            window.clearInterval(id);
        };
    }, [availabilityUrl]);

    // Remove da seleção assentos que deixaram de estar disponíveis, avisando
    // o comprador (senão o assento some do resumo em silêncio).
    useEffect(() => {
        setSelected((prev) => {
            const taken = prev.filter((s) => (statuses[s.id] ?? 'available') !== 'available');

            if (taken.length === 0) {
                return prev;
            }

            veludoToast.warning(
                taken.length === 1
                    ? 'Um assento foi reservado'
                    : 'Alguns assentos foram reservados',
                `${taken.map((s) => s.code).join(', ')} ${taken.length === 1 ? 'acabou' : 'acabaram'} de sair. Escolha ${taken.length === 1 ? 'outro' : 'outros'}.`,
            );

            return prev.filter((s) => (statuses[s.id] ?? 'available') === 'available');
        });
    }, [statuses]);

    function toggle(seat: Seat) {
        if ((statuses[seat.id] ?? seat.status) !== 'available') {
            return;
        }

        setSelected((prev) =>
            prev.some((s) => s.id === seat.id) ? prev.filter((s) => s.id !== seat.id) : [...prev, seat],
        );
    }

    function doReserve(extra: Record<string, unknown>) {
        setSubmitting(true);
        router.post(
            reserveUrl,
            { seats: selectedIds, ...extra },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.seats) {
                        veludoToast.warning('Não foi possível reservar', errors.seats);
                    }

                    setGuestErrors(errors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function reserve() {
        if (selected.length === 0 || submitting) {
            return;
        }

        // Logado segue direto; convidado preenche dados (cria conta leve no backend).
        if (user) {
            doReserve({});
        } else {
            setGuestErrors({});
            setGuestOpen(true);
        }
    }

    function submitGuest() {
        if (submitting) {
            return;
        }

        doReserve({ guest });
    }

    return (
        <div className={className}>
            <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                <SeatMap seats={seats} bounds={seatMap.bounds} selectedIds={selectedIds} onToggle={toggle} />

                <aside className="hidden rounded-card border border-border bg-surface p-6 lg:sticky lg:top-20 lg:block lg:self-start">
                    <SelectionPanel
                        seats={selected}
                        total={total}
                        onRemove={toggle}
                        onClear={() => setSelected([])}
                        onCheckout={reserve}
                        submitting={submitting}
                    />
                </aside>
            </div>

            {/* folga p/ a legenda do mapa não ficar sob a barra fixa + tab bar no mobile */}
            <div aria-hidden="true" className="h-36 lg:hidden" />

            <SeatSheet
                seats={selected}
                total={total}
                onRemove={toggle}
                onClear={() => setSelected([])}
                onCheckout={reserve}
                submitting={submitting}
            />

            <Modal
                open={guestOpen}
                onOpenChange={(o) => !o && !submitting && setGuestOpen(false)}
                title="Quase lá"
                description="Seus dados para emitir os ingressos. Sem senha — criamos seu acesso na hora."
            >
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submitGuest();
                    }}
                    className="flex flex-col gap-4"
                >
                    <FormField label="Nome completo" htmlFor="g-name" error={guestErrors['guest.name']}>
                        <Input
                            id="g-name"
                            value={guest.name}
                            onChange={(e) => setGuest({ ...guest, name: e.target.value })}
                            placeholder="Como no documento"
                            required
                        />
                    </FormField>
                    <FormField
                        label="E-mail"
                        htmlFor="g-email"
                        error={guestErrors['guest.email']}
                        helper="Enviamos o ingresso e o acesso por aqui."
                    >
                        <Input
                            id="g-email"
                            type="email"
                            value={guest.email}
                            onChange={(e) => setGuest({ ...guest, email: e.target.value })}
                            placeholder="nome@email.com"
                            required
                        />
                    </FormField>
                    <FormField label="CPF" htmlFor="g-cpf" error={guestErrors['guest.cpf']}>
                        <Input
                            id="g-cpf"
                            value={guest.cpf}
                            onChange={(e) => setGuest({ ...guest, cpf: e.target.value })}
                            placeholder="000.000.000-00"
                            inputMode="numeric"
                            required
                        />
                    </FormField>
                    <p className="font-body text-xs text-faint">
                        Já tem conta?{' '}
                        <a
                            href={`/login?redirect=${encodeURIComponent(currentUrl)}`}
                            className="text-accent-text hover:underline"
                        >
                            Entrar
                        </a>
                        .
                    </p>
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setGuestOpen(false)}
                            disabled={submitting}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={submitting}>
                            {submitting ? <Spinner /> : 'Continuar para o pagamento'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
