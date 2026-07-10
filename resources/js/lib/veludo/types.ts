// Tipos de domínio (mockados) — espelham o payload enviado pelos controllers.
// Veludo · Sistema de Ingressos.

export type SeatStatus = 'available' | 'held' | 'sold' | 'blocked';
export type SeatKind = 'standard' | 'accessible' | 'companion';

/** Assento posicionado por coordenada real (mapa do Teatro UNIP). */
export interface Seat {
    id: number;
    code: string; // "A10"
    row: string; // "A"
    number: string; // "10" (ou "A" nas fileiras CAD/CAA)
    x: number;
    y: number;
    sectorName: string;
    status: SeatStatus;
    kind: SeatKind;
    price: number;
    visibility?: string;
}

export interface SeatBounds {
    minX: number;
    minY: number;
    maxX: number;
    maxY: number;
}

export interface SeatMapData {
    sectorName: string;
    seats: Seat[];
    bounds: SeatBounds;
}

export interface Sector {
    id: number;
    name: string;
    price: number;
    soldOut?: boolean;
    availableCount?: number;
}

export interface VenueInfo {
    name: string;
    city: string;
    state: string;
    address?: string;
}

export interface EventStatus {
    tone: 'success' | 'warning' | 'danger' | 'accent' | 'info' | 'neutral';
    label: string;
}

export interface EventInfo {
    id: number;
    slug: string;
    title: string;
    kicker: string;
    description: string;
    status: EventStatus;
    venue: VenueInfo;
    dateLabel: string;
    timeLabel: string;
    durationLabel: string;
    bannerFrom: string;
    bannerTo: string;
    bannerImage?: string;
}

export interface SelectedSeat {
    id: number;
    code: string;
    sectorName: string;
    price: number;
}

export interface ReservationInfo {
    expiresAt: string;
    eventTitle: string;
    sessionLabel: string;
    seats: SelectedSeat[];
}

export interface PriceLine {
    label: string;
    value: number;
    tone?: 'default' | 'success' | 'muted';
}

export type TicketStatus = 'valid' | 'used' | 'transferred' | 'cancelled' | 'refunded';

export interface TicketInfo {
    id: number;
    eventTitle: string;
    kicker: string;
    sectorName: string;
    seatLabel: string;
    dateLabel: string;
    venueName: string;
    holderName: string;
    code: string;
    /** Token assinado embutido no QR (validado no check-in). */
    qrToken: string;
    status: TicketStatus;
    statusLabel: string;
    /** Endpoint POST de transferência deste ingresso. */
    transferUrl: string;
    /** Reembolso self-service disponível (pago + dentro do prazo). */
    canRefund: boolean;
    /** Endpoint POST de reembolso do pedido deste ingresso. */
    refundUrl: string;
    /** Download .ics (adicionar à agenda). */
    calendarUrl: string;
    /** Link "Adicionar ao Google Wallet". */
    googleWalletUrl: string;
}
