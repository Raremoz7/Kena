<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\Ticket;
use App\Services\CheckInService;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function __construct(private readonly CheckInService $checkins) {}

    public function show(): Response
    {
        // Só sessões relevantes pra portaria: não canceladas e de hoje/futuras
        // (esconde o histórico antigo que só polui o seletor).
        $eventSessions = EventSession::with('event')
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '>=', now()->subHours(12))
            ->orderBy('starts_at')
            ->get();

        $progress = $this->checkins->progressForSessions($eventSessions);

        $sessions = $eventSessions
            ->map(fn (EventSession $s): array => [
                'id' => $s->id,
                'eventTitle' => $s->event->title,
                'label' => CatalogPresenter::sessionLabel($s),
                'progress' => $progress[$s->id],
            ])->all();

        return Inertia::render('admin/checkin', [
            'sessions' => $sessions,
            'scanUrl' => route('admin.checkin.scan'),
            'lookupUrl' => route('admin.checkin.lookup'),
            'admitUrl' => route('admin.checkin.admit'),
        ]);
    }

    /** Busca ingressos por nome, código ou assento (admissão manual). */
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
        ]);

        $q = $data['q'];
        $results = Ticket::query()
            ->where('session_id', (int) $data['session_id'])
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->where(function ($w) use ($q): void {
                $w->where('holder_name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('seat_code', 'like', "%{$q}%");
            })
            ->orderBy('holder_name')
            ->limit(15)
            ->get()
            ->map(fn (Ticket $t): array => [
                'id' => $t->id,
                'code' => $t->code,
                'holder' => $t->holder_name,
                'seat' => $t->sector_name.' · '.$t->seat_code,
                'used' => $t->status === Ticket::STATUS_USED,
            ])->all();

        return response()->json(['results' => $results]);
    }

    /** Admite manualmente um ingresso localizado pela busca. */
    public function admit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer'],
            'ticket_id' => ['required', 'integer'],
        ]);

        /** @var EventSession $session */
        $session = EventSession::findOrFail((int) $data['session_id']);
        /** @var Ticket $ticket */
        $ticket = Ticket::findOrFail((int) $data['ticket_id']);

        return response()->json($this->checkins->admit($ticket, $session, $request->user()));
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'session_id' => ['required', 'integer'],
        ]);

        /** @var EventSession $session */
        $session = EventSession::findOrFail((int) $data['session_id']);
        $result = $this->checkins->check($data['token'], $session, $request->user());

        return response()->json($result);
    }
}
