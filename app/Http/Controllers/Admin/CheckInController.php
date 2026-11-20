<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\SessionSeat;
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
            'previewUrl' => route('admin.checkin.preview'),
            'lookupUrl' => route('admin.checkin.lookup'),
            'admitUrl' => route('admin.checkin.admit'),
            'mapUrl' => route('admin.checkin.map'),
        ]);
    }

    /**
     * Mapa de cadeiras da portaria: estado de cada assento da sessão
     * (entrou, vendido e falta entrar, livre, bloqueado).
     */
    public function map(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'integer']]);
        $sessionId = (int) $data['session_id'];

        $tickets = Ticket::query()
            ->where('session_id', $sessionId)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->get(['id', 'session_seat_id', 'status', 'holder_name', 'code'])
            ->keyBy('session_seat_id');

        $seats = SessionSeat::query()
            ->with(['seat', 'sector'])
            ->where('session_id', $sessionId)
            ->get()
            ->map(function (SessionSeat $ss) use ($tickets): array {
                /** @var Ticket|null $ticket */
                $ticket = $tickets->get($ss->id);

                $state = match (true) {
                    $ticket !== null => $ticket->status === Ticket::STATUS_USED ? 'in' : 'pending',
                    $ss->status === SessionSeat::STATUS_SOLD => 'pending',
                    $ss->status === SessionSeat::STATUS_AVAILABLE => 'free',
                    default => 'blocked',
                };

                return [
                    'id' => $ss->id,
                    'x' => $ss->seat->pos_x,
                    'y' => $ss->seat->pos_y,
                    'row' => $ss->seat->line,
                    'number' => $ss->seat->number,
                    'sector' => $ss->sector->name,
                    'state' => $state,
                    'holder' => $ticket?->holder_name,
                    'code' => $ticket?->code,
                    'ticketId' => $ticket?->id,
                ];
            })->values()->all();

        return response()->json(['seats' => $seats]);
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

        return response()->json($this->checkins->admit($ticket, $session, $request->user('painel')));
    }

    /**
     * Confere um QR sem consumir o ingresso: a portaria vê quem é e confirma
     * a entrada (via admit) ou cancela. Leituras negadas já ficam registradas.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'session_id' => ['required', 'integer'],
        ]);

        /** @var EventSession $session */
        $session = EventSession::findOrFail((int) $data['session_id']);

        return response()->json($this->checkins->preview($data['token'], $session, $request->user('painel')));
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'session_id' => ['required', 'integer'],
        ]);

        /** @var EventSession $session */
        $session = EventSession::findOrFail((int) $data['session_id']);
        $result = $this->checkins->check($data['token'], $session, $request->user('painel'));

        return response()->json($result);
    }
}
