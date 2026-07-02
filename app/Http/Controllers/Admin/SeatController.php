<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\SessionSeat;
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
