<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
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
            ->get()
            ->map(fn (Event $e): array => CatalogPresenter::listItem($e))
            ->all();

        return Inertia::render('buyer/events', [
            'events' => $events,
            'q' => $q,
        ]);
    }

    public function event(string $slug): Response
    {
        $event = Event::with(['venue', 'sectors', 'sessions'])
            ->where('slug', $slug)
            ->firstOrFail();

        $session = $event->sessions->first();
        abort_if($session === null, 404);

        return Inertia::render('buyer/event', [
            'event' => CatalogPresenter::detail($event),
            'sectors' => CatalogPresenter::sectors($event, $session),
            'sessionId' => $session->id,
            'sessionLabel' => CatalogPresenter::sessionLabel($session),
            'sessions' => CatalogPresenter::sessionsList($event),
            'seatMap' => CatalogPresenter::seatMap($session),
            'reserveUrl' => route('sessions.reserve', ['slug' => $event->slug, 'session' => $session->id]),
        ]);
    }

    public function seats(string $slug, EventSession $session): Response|RedirectResponse
    {
        $session->load('event.venue');
        $event = $session->event;
        abort_if($event->slug !== $slug, 404);

        // Sessão cancelada não recebe mais reservas.
        if ($session->status === 'cancelled') {
            return redirect()->route('events.show', $event->slug);
        }

        return Inertia::render('buyer/seats', [
            'event' => ['slug' => $event->slug, 'title' => $event->title],
            'session' => ['id' => $session->id, 'label' => CatalogPresenter::sessionLabel($session)],
            'seatMap' => CatalogPresenter::seatMap($session),
            'reserveUrl' => route('sessions.reserve', ['slug' => $event->slug, 'session' => $session->id]),
            'availabilityUrl' => route('api.sessions.availability', ['session' => $session->id]),
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
