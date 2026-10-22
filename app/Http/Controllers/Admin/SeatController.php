<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SeatConflictException;
use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\SessionSeat;
use App\Services\AdminTicketService;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeatController extends Controller
{
    public function show(EventSession $session): Response
    {
        $session->load('event');

        return Inertia::render('admin/seats', [
            'event' => ['title' => $session->event->title, 'slug' => $session->event->slug],
            'session' => ['id' => $session->id, 'label' => CatalogPresenter::sessionLabel($session)],
            'seatMap' => CatalogPresenter::seatMap($session),
            'toggleUrl' => route('admin.seats.toggle'),
            'bulkUrl' => route('admin.seats.bulk', $session),
            'issueUrl' => route('admin.seats.issue', $session),
        ]);
    }

    /** Bloqueia ou libera vários lugares de uma vez (ex.: a fileira da gestão). */
    public function bulk(Request $request, EventSession $session): JsonResponse
    {
        $data = $request->validate([
            'session_seat_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'session_seat_ids.*' => ['integer'],
            'action' => ['required', 'in:block,release'],
        ]);

        [$from, $to] = $data['action'] === 'block'
            ? [SessionSeat::STATUS_AVAILABLE, SessionSeat::STATUS_BLOCKED]
            : [SessionSeat::STATUS_BLOCKED, SessionSeat::STATUS_AVAILABLE];

        // Só mexe em quem está no estado de origem: vendido/reservado fica intacto.
        $changed = SessionSeat::query()
            ->where('session_id', $session->id)
            ->whereIn('id', $data['session_seat_ids'])
            ->where('status', $from)
            ->update(['status' => $to, 'hold_expires_at' => null, 'held_by_reservation_id' => null]);

        $statuses = SessionSeat::query()
            ->where('session_id', $session->id)
            ->whereIn('id', $data['session_seat_ids'])
            ->pluck('status', 'id');

        return response()->json(['changed' => $changed, 'statuses' => $statuses]);
    }

    /** Emite ingressos grátis e nominais para os lugares escolhidos. */
    public function issue(Request $request, EventSession $session, AdminTicketService $tickets): JsonResponse
    {
        $data = $request->validate([
            'tickets' => ['required', 'array', 'min:1', 'max:200'],
            'tickets.*.session_seat_id' => ['required', 'integer', 'distinct'],
            'tickets.*.name' => ['required', 'string', 'max:120'],
            'tickets.*.email' => ['required', 'email', 'max:255'],
        ], [
            'tickets.*.name.required' => 'Preencha o nome de cada ingresso.',
            'tickets.*.email.required' => 'Preencha o e-mail de cada ingresso.',
            'tickets.*.email.email' => 'Algum e-mail está inválido.',
        ]);

        try {
            $count = $tickets->issue($session, $data['tickets']);
        } catch (SeatConflictException $e) {
            return response()->json([
                'message' => 'Estes lugares já foram vendidos ou reservados: '.implode(', ', $e->seatCodes).'.',
            ], 422);
        }

        return response()->json([
            'message' => "{$count} ingresso(s) emitido(s). Cada pessoa recebe o QR por e-mail.",
            'statuses' => SessionSeat::query()
                ->whereIn('id', array_column($data['tickets'], 'session_seat_id'))
                ->pluck('status', 'id'),
        ]);
    }

    /** Alterna available ↔ blocked (cortesia/lugar interditado). Não toca vendidos/reservados. */
    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate(['session_seat_id' => ['required', 'integer']]);

        /** @var SessionSeat $seat */
        $seat = SessionSeat::findOrFail($data['session_seat_id']);

        if ($seat->status === SessionSeat::STATUS_AVAILABLE) {
            $seat->update([
                'status' => SessionSeat::STATUS_BLOCKED,
                'hold_expires_at' => null,
                'held_by_reservation_id' => null,
            ]);
        } elseif ($seat->status === SessionSeat::STATUS_BLOCKED) {
            $seat->update(['status' => SessionSeat::STATUS_AVAILABLE]);
        } else {
            return response()->json(['message' => 'Assento vendido ou reservado não pode ser bloqueado.'], 422);
        }

        return response()->json(['id' => $seat->id, 'status' => $seat->status]);
    }
}
