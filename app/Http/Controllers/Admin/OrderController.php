<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Ticket;
use App\Services\RefundService;
use App\Services\SessionCancellationService;
use App\Support\Csv;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use App\Support\SessionOptionsCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request, RefundService $refunds): Response
    {
        $sessionId = $request->integer('session') ?: null;
        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');

        $orders = Order::query()
            ->with(['user', 'session.event', 'items', 'refunds', 'tickets'])
            ->when($sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->when($search !== '', fn ($q) => self::search($q, $search))
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
                // Estorno recusado pelo gateway precisa de ação manual do organizador:
                // a última tentativa registrada falhou e ninguém refez depois.
                'refundFailed' => $o->refunds->sortByDesc('id')->first()?->status === Refund::STATUS_FAILED,
                // Pedido pago com algum ingresso já devolvido avulso.
                'partiallyRefunded' => $o->status === Order::STATUS_PAID
                    && $o->tickets->contains('status', Ticket::STATUS_REFUNDED),
                // Quanto ainda cabe devolver (total menos estornos avulsos concluídos).
                'remaining' => Money::toReais(max(0, $o->total_cents
                    - (int) $o->refunds->where('status', Refund::STATUS_DONE)->sum('amount_cents'))),
                // Ingressos do pedido para o reembolso por assento no painel.
                'tickets' => $o->tickets->map(fn (Ticket $t): array => [
                    'id' => $t->id,
                    'seat' => $t->seat_code,
                    'sector' => $t->sector_name,
                    'holder' => $t->holder_name,
                    'status' => $t->status,
                    'amount' => Money::toReais($refunds->shareCents($o, $t)),
                ])->values()->all(),
                'date' => $o->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/orders', [
            'orders' => $orders,
            'sessions' => SessionOptionsCache::get(),
            'sessionId' => $sessionId,
            'search' => $search,
            'exportUrl' => route('admin.orders.export', $sessionId ? ['session' => $sessionId] : []),
        ]);
    }

    /**
     * Busca livre: referência do pedido, nome/e-mail do comprador, assento,
     * titular e código do ingresso. Cada palavra precisa aparecer em algum
     * desses campos ("maria A12" acha a Maria no assento A12).
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private static function search(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $like = '%'.$word.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('reference', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like))
                    ->orWhereHas('items', fn (Builder $i) => $i->where('seat_code', 'like', $like))
                    ->orWhereHas('tickets', fn (Builder $t) => $t
                        ->where('holder_name', 'like', $like)
                        ->orWhere('code', 'like', $like));
            });
        }

        return $query;
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

    private const GATEWAY_REFUSED = 'O Mercado Pago recusou o estorno. Nada foi alterado — tente de novo em instantes.';

    /** Reembolso do pedido inteiro pelo organizador — sem prazo (a qualquer momento, pedido pago). */
    public function refund(Order $order, RefundService $refunds): RedirectResponse
    {
        try {
            $refund = $refunds->refundOrder($order, 'Reembolsado pelo organizador');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        if ($refund->status === Refund::STATUS_FAILED) {
            return back()->withErrors(['order' => self::GATEWAY_REFUSED]);
        }

        return back();
    }

    /**
     * Reembolso de ingressos avulsos (um ou mais assentos) pelo organizador.
     * Cada ingresso devolve sua fatia proporcional do que foi pago; se não
     * sobrar ingresso válido, o pedido fecha como reembolsado.
     */
    public function refundTickets(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        try {
            $result = $refunds->refundTickets($order, $data['ticket_ids'], 'Reembolsado pelo organizador');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        if ($result->contains('status', Refund::STATUS_FAILED)) {
            return back()->withErrors(['order' => self::GATEWAY_REFUSED]);
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
                Csv::put($out, [
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
