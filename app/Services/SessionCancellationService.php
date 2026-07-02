<?php

namespace App\Services;

use App\Models\EventSession;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Cancela uma sessão: reembolsa todos os pedidos pagos (com e-mail), cancela os
 * pendentes liberando os holds, e marca a sessão como cancelada (some da venda).
 */
class SessionCancellationService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly SeatReservationService $reservations,
    ) {}

    /** @return array{refunded: int, cancelled: int} */
    public function cancel(EventSession $session): array
    {
        $refunded = 0;
        $cancelled = 0;

        // Reembolsa pagos (refundOrder cuida de assentos, ingressos e e-mail).
        $paid = Order::where('session_id', $session->id)
            ->where('status', Order::STATUS_PAID)
            ->get();
        foreach ($paid as $order) {
            $this->refunds->refundOrder($order, 'Sessão cancelada');
            $refunded++;
        }

        // Cancela pendentes e libera os holds.
        $pending = Order::where('session_id', $session->id)
            ->where('status', Order::STATUS_PENDING)
            ->with('reservation')
            ->get();
        foreach ($pending as $order) {
            DB::transaction(function () use ($order): void {
                $order->update(['status' => Order::STATUS_CANCELLED]);
                if ($order->reservation !== null) {
                    $this->reservations->release($order->reservation);
                }
            });
            $cancelled++;
        }

        $session->update(['status' => 'cancelled']);

        return ['refunded' => $refunded, 'cancelled' => $cancelled];
    }
}
