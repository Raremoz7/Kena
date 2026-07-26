<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\SessionSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    private const OVERVIEW_EVENTS = 8;

    private const EVENTS_PER_PAGE = 25;

    public function overview(): Response|RedirectResponse
    {
        // Staff não vê receita/ocupação — vai direto pro check-in.
        if (! Auth::guard('painel')->user()?->canManageOrganization()) {
            return redirect()->route('admin.checkin');
        }

        $capacity = SessionSeat::count();
        $sold = SessionSeat::where('status', SessionSeat::STATUS_SOLD)->count();
        $available = SessionSeat::where('status', SessionSeat::STATUS_AVAILABLE)->count();
        $revenueCents = (int) SessionSeat::where('status', SessionSeat::STATUS_SOLD)->sum('price_cents');

        return Inertia::render('admin/overview', [
            'kpis' => [
                'events' => Event::count(),
                'sessions' => EventSession::count(),
                'capacity' => $capacity,
                'sold' => $sold,
                'available' => $available,
                'occupancy' => $capacity > 0 ? (int) round($sold / $capacity * 100) : 0,
                'revenue' => $revenueCents / 100,
            ],
            // O overview é um resumo: mostra só os primeiros e linka "Ver todos".
            'events' => $this->rowsFor(
                Event::query()->with(['venue', 'sessions'])->orderBy('title')->limit(self::OVERVIEW_EVENTS)->get()
            ),
        ]);
    }

    public function events(): Response
    {
        $events = Event::query()
            ->with(['venue', 'sessions'])
            ->orderBy('title')
            ->paginate(self::EVENTS_PER_PAGE)
            ->withQueryString();

        // Substitui os itens pelas linhas mapeadas, preservando o envelope (data/links).
        // Não usamos through() porque rowsFor() agrega os assentos de todos os
        // eventos da página numa query só — precisa da coleção inteira de uma vez.
        // O paginator troca os models Event por linhas-array do Inertia de
        // propósito; o generics do PHPStan não expressa essa substituição.
        // @phpstan-ignore-next-line argument.type
        $events->setCollection(collect($this->rowsFor($events->getCollection())));

        return Inertia::render('admin/events', [
            'events' => $events,
        ]);
    }

    /**
     * @param  Collection<int, Event>|\Illuminate\Database\Eloquent\Collection<int, Event>  $events
     * @return array<int, array<string, mixed>>
     */
    private function rowsFor($events): array
    {
        $sessionIds = $events->flatMap(fn (Event $e): Collection => $e->sessions->pluck('id'));

        // Uma query agregada por status em vez de 2 counts por evento (evita N+1).
        $capacityBySession = SessionSeat::query()
            ->whereIn('session_id', $sessionIds)
            ->selectRaw('session_id, count(*) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');
        $soldBySession = SessionSeat::query()
            ->whereIn('session_id', $sessionIds)
            ->where('status', SessionSeat::STATUS_SOLD)
            ->selectRaw('session_id, count(*) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');

        return $events
            ->map(function (Event $event) use ($capacityBySession, $soldBySession): array {
                $eventSessionIds = $event->sessions->pluck('id');
                $capacity = (int) $eventSessionIds->sum(fn ($id) => $capacityBySession[$id] ?? 0);
                $sold = (int) $eventSessionIds->sum(fn ($id) => $soldBySession[$id] ?? 0);
                $next = $event->sessions->first();

                return [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'status' => $event->status,
                    // O PHPDoc da relação `venue` é não-nulável, mas ela pode
                    // ser nula em runtime; o ternário é intencional.
                    // @phpstan-ignore-next-line ternary.alwaysTrue
                    'venue' => $event->venue
                        ? trim($event->venue->name.', '.$event->venue->city, ', ')
                        : '—',
                    'sessionsCount' => $event->sessions->count(),
                    'nextDate' => $next?->starts_at?->format('d/m/Y H:i'),
                    'capacity' => $capacity,
                    'sold' => $sold,
                ];
            })
            ->all();
    }
}
