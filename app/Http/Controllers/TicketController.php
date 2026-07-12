<?php

namespace App\Http\Controllers;

use App\Exceptions\TransferException;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\GoogleWalletPass;
use App\Services\RefundService;
use App\Services\TicketTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TicketController extends Controller
{
    /** Redireciona para o "Adicionar ao Google Wallet" (JWT assinado). */
    public function googleWallet(Ticket $ticket, GoogleWalletPass $wallet): RedirectResponse
    {
        abort_unless($ticket->user_id === Auth::id(), 403);

        try {
            $url = $wallet->saveUrl($ticket);
        } catch (\RuntimeException) {
            return back()->withErrors(['wallet' => 'Google Wallet não está configurado no momento.']);
        }

        return redirect()->away($url);
    }

    /** Arquivo .ics (adicionar à agenda) da sessão do ingresso. */
    public function calendar(Ticket $ticket): Response
    {
        abort_unless($ticket->user_id === Auth::id(), 403);

        $ticket->loadMissing('session.event.venue');
        $session = $ticket->session;
        $event = $session->event;
        $venue = $event->venue;

        $fmt = fn ($dt): string => $dt->utc()->format('Ymd\THis\Z');
        $location = $this->icsEscape(trim($venue->name.', '.($venue->address ?? $venue->city)));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kena//Ingressos//PT-BR',
            'BEGIN:VEVENT',
            'UID:'.$ticket->code.'@kena',
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($session->starts_at),
            'DTEND:'.$fmt($session->starts_at->copy()->addHours(2)),
            'SUMMARY:'.$this->icsEscape($event->title),
            'LOCATION:'.$location,
            'DESCRIPTION:'.$this->icsEscape('Ingresso '.$ticket->code.' — '.$ticket->sector_name.' '.$ticket->seat_code),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$ticket->code.'.ics"',
        ]);
    }

    private function icsEscape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    /** Transfere o ingresso para outro titular (com conta). */
    public function transfer(Request $request, Ticket $ticket, TicketTransferService $transfers): JsonResponse
    {
        abort_unless($ticket->user_id === Auth::id(), 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $transfers->transfer($ticket, $request->user(), $data['email']);
        } catch (TransferException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Ingresso transferido para '.$data['email'].'.']);
    }

    /** Reembolso self-service do próprio comprador — permitido até o prazo da sessão. */
    public function refund(Order $order, RefundService $refunds): JsonResponse
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $order->loadMissing('session');
        if (now()->greaterThanOrEqualTo($order->session->refundLocksAt())) {
            return response()->json([
                'message' => 'O prazo de reembolso desta sessão já encerrou. Fale com o organizador.',
            ], 422);
        }

        // Pedido com ingresso transferido não pode ser estornado pelo comprador:
        // o reembolso derrubaria o ingresso válido de quem recebeu.
        if ($order->tickets()->where('status', Ticket::STATUS_TRANSFERRED)->exists()) {
            return response()->json([
                'message' => 'Este pedido tem ingresso transferido e não pode mais ser reembolsado por aqui. Fale com o organizador.',
            ], 422);
        }

        // Ingresso já utilizado na portaria não é reembolsável pelo comprador.
        if ($order->tickets()->where('status', Ticket::STATUS_USED)->exists()) {
            return response()->json([
                'message' => 'Este pedido tem ingresso já utilizado e não pode ser reembolsado por aqui. Fale com o organizador.',
            ], 422);
        }

        try {
            $refunds->refundOrder($order, 'Solicitado pelo comprador');
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Pedido reembolsado. O estorno aparece conforme o prazo do Mercado Pago.']);
    }
}
