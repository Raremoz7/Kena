<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\SessionSeat;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Super admin: eventos de todos os organizadores, com ocupação e vendas. */
class EventController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $organizationId = $request->integer('organizacao') ?: null;

        $events = Event::query()
            ->with(['organization', 'venue', 'sessions'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhereIn('organization_id', Organization::query()->where('name', 'like', "%{$q}%")->select('id'))))
            ->when($organizationId !== null, fn ($query) => $query->where('organization_id', $organizationId))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $sessionIds = $events->getCollection()->flatMap(fn (Event $e) => $e->sessions->pluck('id'));
        $capacity = SessionSeat::query()->whereIn('session_id', $sessionIds)->selectRaw('session_id, count(*) as total')->groupBy('session_id')->pluck('total', 'session_id');
        $sold = SessionSeat::query()->whereIn('session_id', $sessionIds)->where('status', SessionSeat::STATUS_SOLD)->selectRaw('session_id, count(*) as total')->groupBy('session_id')->pluck('total', 'session_id');
        $gross = Order::query()->where('status', Order::STATUS_PAID)->whereIn('session_id', $sessionIds)->selectRaw('session_id, sum(total_cents) as total')->groupBy('session_id')->pluck('total', 'session_id');

        $rows = $events->getCollection()->map(function (Event $event) use ($capacity, $sold, $gross): array {
            $ids = $event->sessions->pluck('id');
            $next = $event->sessions->first(fn (EventSession $s): bool => $s->starts_at->isFuture()) ?? $event->sessions->last();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'status' => $event->status,
                'organization' => $event->organization !== null ? ['id' => $event->organization->id, 'name' => $event->organization->name] : null,
                'venue' => trim(($event->venue->name ?? '').', '.($event->venue->city ?? ''), ', '),
                'nextDate' => $next?->starts_at->format('d/m/Y H:i'),
                'capacity' => (int) $ids->sum(fn ($id) => $capacity[$id] ?? 0),
                'sold' => (int) $ids->sum(fn ($id) => $sold[$id] ?? 0),
                'gross' => Money::toReais((int) $ids->sum(fn ($id) => $gross[$id] ?? 0)),
            ];
        });

        return Inertia::render('super/events', [
            'events' => [
                'data' => $rows->values(),
                'meta' => ['currentPage' => $events->currentPage(), 'lastPage' => $events->lastPage(), 'total' => $events->total()],
                'links' => ['prev' => $events->previousPageUrl(), 'next' => $events->nextPageUrl()],
            ],
            'filters' => ['q' => $q, 'organizacao' => $organizationId],
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
