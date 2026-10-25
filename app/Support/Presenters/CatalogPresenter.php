<?php

namespace App\Support\Presenters;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\SessionSeat;
use App\Services\CourtesyReserveService;
use App\Services\LotSnapshot;
use App\Services\TicketLotService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converte os models do catálogo no payload exato consumido pelas telas do
 * comprador (resources/js/lib/veludo/types.ts).
 */
final class CatalogPresenter
{
    /**
     * Item da listagem (`buyer/events`).
     *
     * @return array<string, mixed>
     */
    public static function listItem(Event $event): array
    {
        $session = $event->sessions->first();
        $priceFrom = ($session ? self::lots()->snapshot($session)->currentPriceCents() : null)
            ?? $event->sectors->min('price_cents') ?? 0;

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'kicker' => $event->kicker,
            'status' => self::status($event->status),
            'venue' => self::venue($event),
            'dateLabel' => $session ? self::dateLabel($session->starts_at) : '',
            'timeLabel' => $session ? self::timeLabel($session->starts_at) : '',
            'bannerFrom' => $event->banner_from,
            'bannerTo' => $event->banner_to,
            'bannerImage' => $event->banner_image,
            'bannerOpacity' => (int) $event->banner_opacity,
            'priceFrom' => Money::toReais((int) $priceFrom),
        ];
    }

    /**
     * Detalhe do evento (`buyer/event` → EventInfo).
     *
     * @return array<string, mixed>
     */
    public static function detail(Event $event): array
    {
        $session = $event->sessions->first();

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'kicker' => $event->kicker,
            'description' => $event->description,
            'status' => self::status($event->status),
            'venue' => self::venue($event),
            'dateLabel' => $session ? self::dateLabel($session->starts_at) : '',
            'timeLabel' => $session ? self::timeLabel($session->starts_at) : '',
            'durationLabel' => $event->duration_label ?? '',
            'bannerFrom' => $event->banner_from,
            'bannerTo' => $event->banner_to,
            'bannerImage' => $event->banner_image,
            'bannerOpacity' => (int) $event->banner_opacity,
        ];
    }

    /**
     * Setores com contagem de disponibilidade da sessão.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sectors(Event $event, EventSession $session, ?LotSnapshot $lots = null): array
    {
        $available = $session->sessionSeats()
            ->where('status', SessionSeat::STATUS_AVAILABLE)
            ->selectRaw('sector_id, count(*) as total')
            ->groupBy('sector_id')
            ->pluck('total', 'sector_id');

        $lotPrice = ($lots ?? self::lots()->snapshot($session))->currentPriceCents();

        return $event->sectors->map(fn ($sector): array => [
            'id' => $sector->id,
            'name' => $sector->name,
            'price' => Money::toReais($lotPrice ?? $sector->price_cents),
            'availableCount' => (int) ($available[$sector->id] ?? 0),
            'soldOut' => (int) ($available[$sector->id] ?? 0) === 0,
        ])->all();
    }

    /**
     * Mapa de assentos da sessão (SeatMapData). O id é o session_seat.
     *
     * @return array<string, mixed>
     */
    public static function seatMap(EventSession $session, ?LotSnapshot $lots = null): array
    {
        /** @var Collection<int, SessionSeat> $seats */
        $seats = $session->sessionSeats()->with(['seat', 'sector'])->get();
        // Com lotes, todo lugar livre custa o preço do lote atual.
        $lotPrice = ($lots ?? self::lots()->snapshot($session))->currentPriceCents();

        $mapped = $seats->map(fn (SessionSeat $ss): array => [
            'id' => $ss->id,
            'code' => $ss->seat->code,
            'row' => $ss->seat->line,
            'number' => $ss->seat->number,
            'x' => $ss->seat->pos_x,
            'y' => $ss->seat->pos_y,
            'sectorName' => $ss->sector->name,
            'status' => $ss->isEffectivelyAvailable() ? 'available' : $ss->status,
            'kind' => $ss->seat->kind,
            'price' => Money::toReais($lotPrice ?? $ss->price_cents),
            'visibility' => self::visibilityForRow($ss->seat->line),
        ]);

        $xs = $seats->map(fn (SessionSeat $ss): int => $ss->seat->pos_x);
        $ys = $seats->map(fn (SessionSeat $ss): int => $ss->seat->pos_y);
        $sectorName = $seats->first()?->sector->name ?? '';

        return [
            'sectorName' => $sectorName,
            'seats' => $mapped->values()->all(),
            'bounds' => [
                'minX' => (int) ($xs->min() ?? 0),
                'minY' => (int) ($ys->min() ?? 0),
                'maxX' => (int) ($xs->max() ?? 0),
                'maxY' => (int) ($ys->max() ?? 0),
            ],
        ];
    }

    public static function sessionLabel(EventSession $session): string
    {
        return ucfirst($session->starts_at->isoFormat('ddd, D MMM')).' · '.self::timeLabel($session->starts_at);
    }

    /**
     * Lista de sessões do evento para o comprador escolher.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sessionsList(Event $event): array
    {
        $sessions = $event->sessions->filter(fn (EventSession $s): bool => $s->status !== 'cancelled');
        $sessionIds = $sessions->pluck('id');

        // Uma query agregada para todas as sessões em vez de 1 count por sessão.
        $available = SessionSeat::whereIn('session_id', $sessionIds)
            ->where('status', SessionSeat::STATUS_AVAILABLE)
            ->selectRaw('session_id, count(*) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');

        return $sessions
            ->map(fn (EventSession $s): array => [
                'id' => $s->id,
                'label' => self::sessionLabel($s),
                'dateLabel' => self::dateLabel($s->starts_at),
                'timeLabel' => self::timeLabel($s->starts_at),
                'availableCount' => (int) ($available[$s->id] ?? 0),
                'seatsUrl' => route('sessions.seats', ['slug' => $event->slug, 'session' => $s->id]),
            ])->values()->all();
    }

    private static function lots(): TicketLotService
    {
        return app(TicketLotService::class);
    }

    /**
     * Lote vendendo agora e o próximo, para o site mostrar "1º lote · R$ 45".
     *
     * @return array{current: array{name: string, price: float, remaining: int|null}|null, next: array{name: string, price: float}|null, soldOut: bool}|null
     */
    public static function lotInfo(EventSession $session, ?LotSnapshot $lots = null): ?array
    {
        $summary = ($lots ?? self::lots()->snapshot($session))->summary();
        if ($summary === []) {
            return null;
        }

        $current = collect($summary)->firstWhere('state', 'current');
        $next = collect($summary)->firstWhere('state', 'next');

        return [
            'current' => $current ? [
                'name' => $current['name'],
                'price' => Money::toReais($current['priceCents']),
                'remaining' => $current['remaining'],
            ] : null,
            'next' => $next ? ['name' => $next['name'], 'price' => Money::toReais($next['priceCents'])] : null,
            'soldOut' => $current === null,
        ];
    }

    /**
     * A venda paga esgotou, mas a sessão ainda guarda lugares de cortesia:
     * o mapa continua aberto, e o pai escolhe e informa o código no pagamento.
     */
    public static function courtesyOnly(EventSession $session, ?LotSnapshot $lots = null): bool
    {
        if ((int) $session->courtesy_reserve <= 0) {
            return false;
        }

        return app(CourtesyReserveService::class)->paidLeft($session) === 0
            || ($lots ?? self::lots()->snapshot($session))->soldOut();
    }

    /** @return array<string, mixed> */
    private static function venue(Event $event): array
    {
        return [
            'name' => $event->venue->name,
            'city' => $event->venue->city,
            'state' => $event->venue->state,
            'address' => $event->venue->address,
        ];
    }

    /** @return array{tone: string, label: string} */
    private static function status(string $status): array
    {
        return match ($status) {
            'sold_out' => ['tone' => 'danger', 'label' => 'Esgotado'],
            'draft' => ['tone' => 'neutral', 'label' => 'Em breve'],
            'closed' => ['tone' => 'neutral', 'label' => 'Encerrado'],
            default => ['tone' => 'success', 'label' => 'À venda'],
        };
    }

    private static function dateLabel(CarbonInterface $dt): string
    {
        return ucfirst($dt->isoFormat('ddd, D [de] MMMM'));
    }

    public static function timeLabel(CarbonInterface $dt): string
    {
        return $dt->format('H\\hi');
    }

    private static function visibilityForRow(string $row): string
    {
        if ($row === 'CAD' || $row === 'CAA') {
            return 'Acessível';
        }
        $letter = substr($row, 0, 1);
        if ($letter <= 'G') {
            return 'Ótima';
        }
        if ($letter <= 'N') {
            return 'Boa';
        }

        return 'Regular';
    }
}
