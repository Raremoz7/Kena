<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Ticket;
use App\Services\RefundService;
use App\Services\SessionCancellationService;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use App\Support\SessionOptionsCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $sessionId = $request->integer('session') ?: null;

        $orders = Order::query()
            ->with(['user', 'session.event', 'items', 'refunds'])
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o): array => [
                'id' => $o->id,
                'reference' => $o->reference,
                'buyer' => $o->user->name,
                'email' => $o->user->email,
                'event' => $o->session->event->title,
                'sessionLabel' => CatalogPresenter::sessionLabel($o->session),
                'seats' => $o->items->pluck('seat_code')->implode(', '),
                'total' => Money::toReais($o->total_cents),
                'status' => $o->status,
                // Estorno recusado pelo gateway precisa de ação manual do organizador.
                'refundFailed' => $o->refunds->contains('status', Refund::STATUS_FAILED)
                    && ! $o->refunds->contains('status', Refund::STATUS_DONE),
                'date' => $o->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/orders', [
            'orders' => $orders,
            'sessions' => SessionOptionsCache::get(),
            'sessionId' => $sessionId,
            'exportUrl' => route('admin.orders.export', $sessionId ? ['session' => $sessionId] : []),
        ]);
    }

    /** Cancela a sessão e reembolsa todos os pedidos pagos em massa. */
    public function cancelSession(EventSession $session, SessionCancellationService $service): RedirectResponse
    {
        $report = $service->cancel($session);

        $summary = "Sessão cancelada: {$report['refunded']} reembolsado(s), {$report['cancelled']} pendente(s) cancelado(s).";
        if ($report['failed'] > 0) {
            return back()->withErrors([
                'session' => $summary." ATENÇÃO: {$report['failed']} estorno(s) FALHARAM no gateway — reprocesse pelo botão de reembolso do pedido.",
            ]);
        }

        return back()->with('status', $summary);
    }

    /** Reembolso pelo organizador — sem prazo (a qualquer momento, pedido pago). */
    public function refund(Order $order, RefundService $refunds): RedirectResponse
    {
        try {
            $refunds->refundOrder($order, 'Reembolsado pelo organizador');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return back();
    }

    /** Lista de participantes (1 linha por ingresso) em CSV — para portaria/contabilidade. */
    public function exportAttendees(Request $request): StreamedResponse
    {
        $sessionId = $request->integer('session') ?: null;

        $tickets = Ticket::query()
            ->with(['session.event', 'order.user'])
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->orderBy('session_id')
            ->orderBy('seat_code')
            ->get();

        $filename = 'participantes'.($sessionId ? "-sessao-{$sessionId}" : '').'.csv';

        return response()->streamDownload(function () use ($tickets): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Código', 'Titular', 'Setor', 'Assento', 'Evento', 'Sessão', 'Pedido', 'E-mail', 'Status', 'Check-in']);
            foreach ($tickets as $t) {
                fputcsv($out, [
                    $t->code,
                    $t->holder_name,
                    $t->sector_name,
                    $t->seat_code,
                    $t->session->event->title,
                    CatalogPresenter::sessionLabel($t->session),
                    $t->order->reference,
                    $t->order->user->email,
                    $t->status,
                    $t->checked_in_at?->format('d/m/Y H:i') ?? '',
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
