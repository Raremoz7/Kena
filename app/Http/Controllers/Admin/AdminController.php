<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\SessionSeat;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function overview(): Response
    {
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
            'events' => $this->eventRows(),
        ]);
    }

    public function events(): Response
    {
        return Inertia::render('admin/events', [
            'events' => $this->eventRows(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function eventRows(): array
    {
        return Event::query()
            ->with(['venue', 'sessions'])
            ->orderBy('title')
            ->get()
            ->map(function (Event $event): array {
                $sessionIds = $event->sessions->pluck('id');
                $capacity = SessionSeat::whereIn('session_id', $sessionIds)->count();
                $sold = SessionSeat::whereIn('session_id', $sessionIds)
                    ->where('status', SessionSeat::STATUS_SOLD)
                    ->count();
                $next = $event->sessions->first();

                return [
                    'id' => $event->id,
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'kicker' => $event->kicker,
                    'status' => $event->status,
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
