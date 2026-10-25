<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use App\Services\LotSnapshot;
use App\Services\SeatReservationService;
use App\Services\TicketLotService;
use App\Support\GoogleWalletSettings;
use App\Support\Presenters\CatalogPresenter;
use App\Support\Presenters\TicketPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Telas do comprador. Catálogo e mapa de assentos já vêm do banco (CatalogPresenter);
 * checkout/ingressos seguem em fases próprias.
 */
class BuyerController extends Controller
{
    private const EVENTS_PER_PAGE = 12;

    public function events(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $events = Event::with(['venue', 'sectors', 'sessions'])
            ->whereIn('status', ['on_sale', 'sold_out'])
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($w) use ($q): void {
                    $w->where('title', 'like', "%{$q}%")
                        ->orWhereHas('venue', fn ($v) => $v->where('name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%"));
                });
            })
            // Ordem determinística: sem ela a paginação repete/pula eventos entre páginas.
            ->orderBy('id')
            ->paginate(self::EVENTS_PER_PAGE)
            ->withQueryString()
            ->through(fn (Event $e): array => CatalogPresenter::listItem($e));

        return Inertia::render('buyer/events', [
            // scroll() = paginator + metadata de página; o <InfiniteScroll> do front
            // concatena em `events.data` a cada partial reload. A busca é visita
            // completa, então substitui a lista em vez de concatenar.
            'events' => Inertia::scroll($events),
            'q' => $q,
        ]);
    }

    public function event(string $slug): Response
    {
        $event = Event::with(['venue', 'sectors', 'sessions'])
            ->where('slug', $slug)
            ->whereIn('status', ['on_sale', 'sold_out']) // draft/encerrado não é público
            ->firstOrFail();

        // Só sessões vendáveis alimentam o mapa/CTA da página.
        $session = $event->sessions->first(fn (EventSession $s): bool => $s->isSellable());
        abort_if($session === null, 404);

        $sessions = CatalogPresenter::sessionsList($event);

        // Com várias sessões a página só lista links — o mapa nunca é renderizado,
        // então nem sai do banco. Com sessão única ele fica abaixo da dobra: sai do
        // payload inicial e chega numa segunda requisição.
        $multi = count($sessions) > 1;

        // Um retrato dos lotes para a página toda (preço dos setores e lote atual).
        // Em closure: o carregamento adiado do mapa (partial reload) não recalcula.
        $lots = null;
        $snapshot = function () use (&$lots, $session): LotSnapshot {
            return $lots ??= app(TicketLotService::class)->snapshot($session);
        };

        return Inertia::render('buyer/event', [
            'event' => CatalogPresenter::detail($event),
            'sectors' => fn (): array => CatalogPresenter::sectors($event, $session, $snapshot()),
            'sessionId' => $session->id,
            'sessionLabel' => CatalogPresenter::sessionLabel($session),
            'sessions' => $sessions,
            'lot' => fn (): ?array => $multi ? null : CatalogPresenter::lotInfo($session, $snapshot()),
            'courtesyOnly' => fn (): bool => ! $multi && CatalogPresenter::courtesyOnly($session, $snapshot()),
            'seatMap' => $multi ? null : Inertia::defer(fn (): array => CatalogPresenter::seatMap($session)),
            'reserveUrl' => route('sessions.reserve', ['slug' => $event->slug, 'session' => $session->id]),
            'availabilityUrl' => route('api.sessions.availability', ['session' => $session->id]),
            'emailCodeUrl' => route('email-code.send'),
        ]);
    }

    public function seats(string $slug, EventSession $session, SeatReservationService $reservations): Response|RedirectResponse
    {
        $session->load('event.venue');
        $event = $session->event;
        abort_if($event->slug !== $slug, 404);

        // Sessão não vendável (cancelada, passada ou evento fora de venda)
        // não recebe mais reservas.
        if (! $session->isSellable()) {
            return redirect()->route('events.index');
        }

        $lots = app(TicketLotService::class)->snapshot($session);

        return Inertia::render('buyer/seats', [
            'event' => ['slug' => $event->slug, 'title' => $event->title],
            'session' => ['id' => $session->id, 'label' => CatalogPresenter::sessionLabel($session)],
            'seatMap' => CatalogPresenter::seatMap($session, $lots),
            'lot' => CatalogPresenter::lotInfo($session, $lots),
            'courtesyOnly' => CatalogPresenter::courtesyOnly($session, $lots),
            'reserveUrl' => route('sessions.reserve', ['slug' => $event->slug, 'session' => $session->id]),
            'availabilityUrl' => route('api.sessions.availability', ['session' => $session->id]),
            'emailCodeUrl' => route('email-code.send'),
            // Quantos assentos este comprador ainda pode selecionar (limite por comprador).
            'maxSeats' => $reservations->remainingFor($session, Auth::guard('web')->user()),
        ]);
    }

    public function tickets(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        return Inertia::render('buyer/tickets', [
            'tickets' => TicketPresenter::forUser($user),
            'needsPassword' => $user->password === null,
            'googleWalletEnabled' => GoogleWalletSettings::isConfigured(),
        ]);
    }

    public function styleGuide(): Response
    {
        return Inertia::render('style-guide');
    }
}
