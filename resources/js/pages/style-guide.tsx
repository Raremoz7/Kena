import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Badge } from '@/components/atoms/Badge';
import { Button } from '@/components/atoms/Button';
import { Countdown } from '@/components/atoms/Countdown';
import { Icon  } from '@/components/atoms/Icon';
import type {IconName} from '@/components/atoms/Icon';
import { Input } from '@/components/atoms/Input';
import { Price } from '@/components/atoms/Price';
import { Spinner } from '@/components/atoms/Spinner';
import { Tag } from '@/components/atoms/Tag';
import { Card } from '@/components/molecules/Card';
import { CouponInput } from '@/components/molecules/CouponInput';
import { EmptyState } from '@/components/molecules/EmptyState';
import { FormField } from '@/components/molecules/FormField';
import { PriceSummary } from '@/components/molecules/PriceSummary';
import { TicketStub } from '@/components/molecules/TicketStub';
import { SeatMap } from '@/components/organisms/SeatMap';
import { veludoToast } from '@/lib/veludo/toast';
import type { Seat, SeatBounds, TicketInfo } from '@/lib/veludo/types';

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="border-t border-border py-8">
            <h2 className="kicker mb-5 text-faint">{title}</h2>
            {children}
        </section>
    );
}

function Swatch({ name, className }: { name: string; className: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className={`size-9 rounded-btn border border-border ${className}`} />
            <span className="font-mono text-[11px] text-muted-foreground">{name}</span>
        </div>
    );
}

const demoTicket: TicketInfo = {
    id: 1,
    eventTitle: 'O Quebra-Nozes',
    kicker: 'Plateia',
    sectorName: 'Plateia',
    seatLabel: 'J1',
    dateLabel: 'Sáb, 19 dez · 20h00',
    venueName: 'Teatro UNIP',
    holderName: 'Helena Drummond',
    code: 'KNA-7Q2X-J1K9-M4P1',
    qrToken: 'KNA-7Q2X-J1K9-M4P1.demo.0000',
    price: 45,
    status: 'valid',
    statusLabel: 'Válido',
    transferUrl: '#',
    canRefund: false,
    refundUrl: '#',
    calendarUrl: '#',
    googleWalletUrl: '#',
};

function seat(id: number, n: number, x: number, status: Seat['status'], kind: Seat['kind'] = 'standard'): Seat {
    return {
        id,
        code: `A${n}`,
        row: 'A',
        number: String(n),
        x,
        y: 40,
        sectorName: 'Plateia',
        status,
        kind,
        price: 45,
        visibility: 'Ótima',
    };
}

const demoSeats: Seat[] = [
    seat(1, 1, 40, 'available'),
    seat(2, 2, 64, 'available'),
    seat(3, 3, 88, 'sold'),
    seat(4, 4, 112, 'held'),
    seat(5, 5, 136, 'blocked'),
    seat(6, 6, 160, 'available', 'accessible'),
    seat(7, 7, 184, 'available', 'companion'),
    seat(8, 8, 208, 'available'),
];
const demoBounds: SeatBounds = { minX: 40, minY: 40, maxX: 208, maxY: 40 };

const icons: IconName[] = [
    'check', 'close', 'alert', 'lock', 'wheelchair', 'clock', 'calendar', 'map-pin', 'ticket',
    'user', 'qr', 'chevron-right', 'arrow-right', 'credit-card', 'pix', 'tag', 'shield', 'transfer',
    'refund', 'search', 'info', 'eye', 'sparkle', 'agenda',
];

export default function StyleGuide() {
    const [sel, setSel] = useState<number[]>([1]);
    const expires = useMemo(() => new Date(Date.now() + 95_000).toISOString(), []);

    return (
        <>
            <Head title="Veludo · Style Guide" />
            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
                <Tag>Design System</Tag>
                <h1 className="mt-2 font-display text-display-lg text-foreground uppercase">Veludo · Componentes</h1>
                <p className="mt-1 font-body text-sm text-muted-foreground">
                    Referência viva dos tokens e componentes. Compare com os mockups <code className="font-mono text-faint">.dc.html</code>.
                </p>

                <Section title="Tipografia">
                    <div className="flex flex-col gap-2">
                        <p className="font-display text-display-xl text-foreground uppercase">Display XL</p>
                        <p className="font-display text-display-lg text-foreground uppercase">Display LG</p>
                        <p className="font-display text-display-md text-foreground uppercase">Display MD</p>
                        <p className="font-display text-display-sm text-foreground uppercase">Display SM</p>
                        <p className="kicker text-muted-foreground">Kicker · categoria · setor</p>
                        <p className="font-body text-base text-foreground">Corpo — Hanken Grotesk para textos e labels.</p>
                    </div>
                </Section>

                <Section title="Cores semânticas">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Swatch name="bg" className="bg-bg" />
                        <Swatch name="surface" className="bg-surface" />
                        <Swatch name="surface-2" className="bg-surface-2" />
                        <Swatch name="accent" className="bg-accent" />
                        <Swatch name="success" className="bg-success" />
                        <Swatch name="warning" className="bg-warning" />
                        <Swatch name="danger" className="bg-danger" />
                        <Swatch name="info" className="bg-info" />
                        <Swatch name="seat-available" className="border-[1.5px] border-seat-available !bg-transparent" />
                        <Swatch name="seat-selected" className="bg-seat-selected" />
                        <Swatch name="seat-held" className="border-[1.5px] border-dashed border-seat-held !bg-transparent" />
                        <Swatch name="seat-pcd" className="bg-seat-pcd" />
                    </div>
                </Section>

                <Section title="Botões">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button>Primary</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="success">Success</Button>
                        <Button variant="danger">Danger</Button>
                        <Button disabled>Disabled</Button>
                        <Button size="sm">Small</Button>
                    </div>
                </Section>

                <Section title="Badges & Tags">
                    <div className="flex flex-wrap items-center gap-3">
                        <Badge tone="success">À venda</Badge>
                        <Badge tone="warning">Últimos lugares</Badge>
                        <Badge tone="danger">Esgotado</Badge>
                        <Badge tone="accent">Destaque</Badge>
                        <Badge tone="info">PCD</Badge>
                        <Badge tone="neutral">Rascunho</Badge>
                        <Tag>Teatro · Musical</Tag>
                    </div>
                </Section>

                <Section title="Inputs & Forms">
                    <div className="grid max-w-md gap-4">
                        <FormField label="E-mail" htmlFor="sg-email" helper="Usado para enviar os ingressos.">
                            <Input id="sg-email" placeholder="nome@email.com" />
                        </FormField>
                        <FormField label="Campo com erro" htmlFor="sg-err" error="Cupom inválido ou expirado.">
                            <Input id="sg-err" invalid defaultValue="XXX" />
                        </FormField>
                        <CouponInput onApply={() => {}} />
                    </div>
                </Section>

                <Section title="Preço, Spinner, Countdown, Avatar">
                    <div className="flex flex-wrap items-center gap-6">
                        <Price value={89} className="text-display-md text-foreground" />
                        <Spinner />
                        <Countdown expiresAt={expires} />
                        <Avatar name="Helena Drummond" />
                    </div>
                </Section>

                <Section title="Toasts">
                    <div className="flex flex-wrap gap-3">
                        <Button size="sm" variant="secondary" onClick={() => veludoToast.success('Cupom aplicado', '10% de desconto.')}>Success</Button>
                        <Button size="sm" variant="secondary" onClick={() => veludoToast.warning('Reserva expirada', 'Assentos liberados.')}>Warning</Button>
                        <Button size="sm" variant="secondary" onClick={() => veludoToast.error('Assento indisponível', 'A2 acabou de ser reservado.')}>Erro</Button>
                        <Button size="sm" variant="secondary" onClick={() => veludoToast.info('Pix pendente', 'Aguardando confirmação.')}>Info</Button>
                    </div>
                </Section>

                <Section title="Ícones (set curado)">
                    <div className="flex flex-wrap gap-4 text-muted-foreground">
                        {icons.map((n) => (
                            <span key={n} className="flex flex-col items-center gap-1">
                                <Icon name={n} size={22} />
                                <span className="font-mono text-[9px] text-faint">{n}</span>
                            </span>
                        ))}
                    </div>
                </Section>

                <Section title="Card, EmptyState, PriceSummary">
                    <div className="grid gap-5 md:grid-cols-3">
                        <Card>
                            <p className="font-display text-display-sm text-foreground uppercase">Card</p>
                            <p className="mt-2 font-body text-sm text-muted-foreground">Superfície com intenção.</p>
                        </Card>
                        <Card>
                            <EmptyState
                                title="Nenhum ingresso"
                                description="Quando comprar, aparece aqui."
                                action={<Button size="sm">Explorar</Button>}
                            />
                        </Card>
                        <Card>
                            <PriceSummary
                                lines={[
                                    { label: '2 × Plateia', value: 90 },
                                    { label: 'Cupom NOITE10', value: -9, tone: 'success' },
                                    { label: 'Taxa', value: 8, tone: 'muted' },
                                ]}
                                total={89}
                            />
                        </Card>
                    </div>
                </Section>

                <Section title="TicketStub">
                    <div className="max-w-md">
                        <TicketStub
                            ticket={demoTicket}
                            actions={
                                <>
                                    <Button size="sm" variant="secondary">
                                        <Icon name="eye" size={15} /> Ver QR
                                    </Button>
                                    <Button size="sm" variant="ghost">
                                        <Icon name="transfer" size={15} /> Transferir
                                    </Button>
                                </>
                            }
                        />
                    </div>
                </Section>

                <Section title="SeatMap (estados de assento)">
                    <div className="max-w-xl">
                        <SeatMap
                            seats={demoSeats}
                            bounds={demoBounds}
                            selectedIds={sel}
                            onToggle={(s) =>
                                setSel((p) => (p.includes(s.id) ? p.filter((i) => i !== s.id) : [...p, s.id]))
                            }
                        />
                    </div>
                </Section>
            </div>
        </>
    );
}
