<?php

namespace App\Services;

use App\Mail\RefundConfirmedMail;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SessionSeat;
use App\Models\Ticket;
use App\Services\Payments\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Reembolso de um pedido pago — inteiro ou por ingresso. Estorna no gateway,
 * cancela os ingressos atingidos e libera os assentos deles.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CouponService $coupons,
    ) {}

    public function refundOrder(Order $order, ?string $reason = null): Refund
    {
        $order->loadMissing(['payment', 'tickets', 'session']);

        // Claim atômico: só quem virar o status PAID→REFUNDED prossegue —
        // duas requisições simultâneas não registram dois estornos.
        $claimed = Order::whereKey($order->id)
            ->where('status', Order::STATUS_PAID)
            ->update(['status' => Order::STATUS_REFUNDED]);

        if ($claimed === 0) {
            throw new \RuntimeException('Só pedidos pagos podem ser reembolsados.');
        }

        // Ingressos já estornados avulsos saem da conta: devolve só o que falta.
        $remaining = $this->remainingCents($order);

        $payment = $order->payment;
        $gatewayOk = true;
        if ($payment !== null && $payment->gateway_payment_id !== null && $remaining > 0) {
            $full = $remaining === $order->total_cents;
            $gatewayOk = $this->gateway->refund(
                $payment->gateway_payment_id,
                $full ? null : $remaining,
                $full ? null : 'refund-'.$payment->gateway_payment_id.'-order-'.$remaining,
            );
        }

        if (! $gatewayOk) {
            // Estorno recusado: devolve o pedido a PAID e registra a falha
            // para o painel — nada de assento/ingresso muda.
            $order->update(['status' => Order::STATUS_PAID]);

            return Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $remaining,
                'reason' => $reason,
                'status' => Refund::STATUS_FAILED,
                'gateway_refund_id' => null,
            ]);
        }

        return $this->settle($order, $reason, $remaining);
    }

    /**
     * Reembolsa ingressos avulsos de um pedido pago (um ou vários de uma vez).
     * Cada ingresso devolve a fatia proporcional do que foi efetivamente pago
     * (cupom e taxa rateados); se depois disso não sobrar ingresso válido no
     * pedido, ele fecha como reembolsado e devolve até o último centavo.
     *
     * @param  list<int>  $ticketIds
     * @return Collection<int, Refund> um registro por ingresso (done ou failed)
     *
     * @throws \RuntimeException pedido não pago, ingresso de outro pedido ou já inválido
     */
    public function refundTickets(Order $order, array $ticketIds, ?string $reason = null): Collection
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));
        if ($ticketIds === []) {
            throw new \RuntimeException('Selecione ao menos um ingresso para reembolsar.');
        }

        $order->loadMissing(['payment', 'session']);

        // Claim atômico dos ingressos: lê com lock, exige todos VÁLIDOS e deste
        // pedido, e só então vira para REFUNDED — check-in, transferência ou
        // outro reembolso concorrente batem na porta fechada.
        /** @var Collection<int, Ticket> $tickets */
        $tickets = DB::transaction(function () use ($order, $ticketIds): Collection {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== Order::STATUS_PAID) {
                throw new \RuntimeException('Só pedidos pagos podem ser reembolsados.');
            }

            $tickets = Ticket::whereKey($ticketIds)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            if ($tickets->count() !== count($ticketIds)) {
                throw new \RuntimeException('Ingresso não encontrado neste pedido.');
            }

            foreach ($tickets as $ticket) {
                if ($ticket->status !== Ticket::STATUS_VALID) {
                    throw new \RuntimeException(self::notRefundableMessage($ticket));
                }
            }

            Ticket::whereKey($ticketIds)->update(['status' => Ticket::STATUS_REFUNDED]);

            return $tickets;
        });

        // Fecha o pedido se não sobrou ingresso vivo (válido ou já usado na
        // portaria). Um ingresso transferido conta pelo novo ingresso válido
        // do destinatário, que também pertence a este pedido.
        $completesOrder = ! Ticket::where('order_id', $order->id)
            ->whereIn('status', [Ticket::STATUS_VALID, Ticket::STATUS_USED])
            ->exists();

        $remaining = $this->remainingCents($order);
        $shares = $this->shares($order, $tickets, $completesOrder ? $remaining : null);
        $amount = (int) array_sum($shares);

        $payment = $order->payment;
        $gatewayOk = true;
        if ($payment !== null && $payment->gateway_payment_id !== null && $amount > 0) {
            sort($ticketIds);
            $gatewayOk = $this->gateway->refund(
                $payment->gateway_payment_id,
                // Devolvendo tudo o que entrou, pede estorno total — sem valor.
                $amount === $order->total_cents ? null : $amount,
                'refund-'.$payment->gateway_payment_id.'-tickets-'.implode('.', $ticketIds),
            );
        }

        if (! $gatewayOk) {
            // Estorno recusado: os ingressos voltam a valer e a falha fica
            // registrada por ingresso para o painel sinalizar.
            Ticket::whereKey($ticketIds)->update(['status' => Ticket::STATUS_VALID]);

            return $tickets->map(fn (Ticket $ticket): Refund => Refund::create([
                'order_id' => $order->id,
                'ticket_id' => $ticket->id,
                'amount_cents' => $shares[$ticket->id],
                'reason' => $reason,
                'status' => Refund::STATUS_FAILED,
                'gateway_refund_id' => null,
            ]));
        }

        $refunds = DB::transaction(function () use ($order, $tickets, $shares, $reason, $completesOrder, $payment): Collection {
            $refunds = new Collection;
            $sessionUpcoming = $order->session->starts_at->isFuture();

            foreach ($tickets as $ticket) {
                $refunds->push(Refund::create([
                    'order_id' => $order->id,
                    'ticket_id' => $ticket->id,
                    'amount_cents' => $shares[$ticket->id],
                    'reason' => $reason,
                    'status' => Refund::STATUS_DONE,
                    'gateway_refund_id' => null,
                ]));

                if ($sessionUpcoming) {
                    $this->releaseSeat($order, $ticket);
                }
            }

            if ($completesOrder) {
                Order::whereKey($order->id)
                    ->where('status', Order::STATUS_PAID)
                    ->update(['status' => Order::STATUS_REFUNDED]);
                $payment?->update(['status' => 'refunded']);
                // Sem ingresso restante, o cupom volta a valer — como no total.
                $this->coupons->release($order);
            }

            return $refunds;
        });

        $order->loadMissing('user');
        if (filled($order->user->email)) {
            Mail::to($order->user->email)->queue(new RefundConfirmedMail(
                $order,
                $reason,
                amountCents: $amount,
                seatCodes: $tickets->pluck('seat_code')->values()->all(),
                partial: ! $completesOrder,
            ));
        }

        return $refunds;
    }

    /**
     * Fatia do total pago que cabe a um ingresso — o que o painel mostra antes
     * de confirmar e o que o estorno devolve. Rateia cupom e taxa pelo preço
     * de tabela de cada assento, arredondando para baixo (nunca devolve mais
     * do que entrou; o resto vai no último ingresso do pedido).
     */
    public function shareCents(Order $order, Ticket $ticket): int
    {
        if ($order->subtotal_cents <= 0) {
            return 0;
        }

        return intdiv($ticket->price_cents * $order->total_cents, $order->subtotal_cents);
    }

    /** Quanto do pedido ainda não foi devolvido (total menos estornos avulsos concluídos). */
    public function remainingCents(Order $order): int
    {
        $done = (int) Refund::where('order_id', $order->id)
            ->where('status', Refund::STATUS_DONE)
            ->sum('amount_cents');

        return max(0, $order->total_cents - $done);
    }

    /**
     * Estorno feito fora do Kena (painel do Mercado Pago ou chargeback do banco):
     * o dinheiro já voltou, então só cancela ingressos e libera os assentos.
     * Devolve null se o pedido já não estava pago (ex.: reembolso do painel do
     * Kena, cujo webhook de "refunded" chega depois).
     */
    public function recordExternalRefund(Order $order, string $reason): ?Refund
    {
        $order->loadMissing(['payment', 'tickets', 'session']);

        $claimed = Order::whereKey($order->id)
            ->where('status', Order::STATUS_PAID)
            ->update(['status' => Order::STATUS_REFUNDED]);

        if ($claimed === 0) {
            return null;
        }

        return $this->settle($order, $reason, $this->remainingCents($order));
    }

    /**
     * Valor por ingresso deste lote. Quando o lote fecha o pedido, o último
     * ingresso leva a diferença de arredondamento para a soma bater com o que
     * ainda faltava devolver.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return array<int, int> ticket_id => centavos
     */
    private function shares(Order $order, Collection $tickets, ?int $exactTotal): array
    {
        $shares = [];
        foreach ($tickets as $ticket) {
            $shares[$ticket->id] = $this->shareCents($order, $ticket);
        }

        $last = $tickets->last();
        if ($exactTotal !== null && $last !== null) {
            $shares[$last->id] = max(0, $exactTotal - (array_sum($shares) - $shares[$last->id]));
        }

        return $shares;
    }

    /**
     * Devolve o assento à venda — só se ainda é deste pedido. Um assento de
     * ingresso já estornado pode ter sido revendido a outro comprador.
     */
    private function releaseSeat(Order $order, Ticket $ticket): void
    {
        SessionSeat::where('id', $ticket->session_seat_id)
            ->where('sold_by_order_id', $order->id)
            ->update([
                'status' => SessionSeat::STATUS_AVAILABLE,
                'sold_by_order_id' => null,
            ]);
    }

    private static function notRefundableMessage(Ticket $ticket): string
    {
        return match ($ticket->status) {
            Ticket::STATUS_USED => "O ingresso do assento {$ticket->seat_code} já foi usado na portaria e não pode ser reembolsado.",
            Ticket::STATUS_TRANSFERRED => "O ingresso do assento {$ticket->seat_code} foi transferido; reembolse o ingresso do novo titular.",
            Ticket::STATUS_REFUNDED => "O ingresso do assento {$ticket->seat_code} já foi reembolsado.",
            default => "O ingresso do assento {$ticket->seat_code} não está mais válido para reembolso.",
        };
    }

    /** Registra o reembolso do pedido inteiro, cancela ingressos, libera assentos e avisa o comprador. */
    private function settle(Order $order, ?string $reason, int $amountCents): Refund
    {
        $payment = $order->payment;

        $refund = DB::transaction(function () use ($order, $payment, $reason, $amountCents): Refund {
            $refund = Refund::create([
                'order_id' => $order->id,
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'status' => Refund::STATUS_DONE,
                'gateway_refund_id' => null,
            ]);

            // Assento só volta pra venda se a sessão ainda não começou —
            // reembolso tardio não pode recolocar lugar de show em andamento.
            $sessionUpcoming = $order->session->starts_at->isFuture();

            foreach ($order->tickets as $ticket) {
                $ticket->update(['status' => Ticket::STATUS_REFUNDED]);
                if ($sessionUpcoming) {
                    $this->releaseSeat($order, $ticket);
                }
            }

            if ($payment !== null) {
                $payment->update(['status' => 'refunded']);
            }

            // Cupom/cortesia volta a valer: o pai que estornou a cortesia para
            // trocar de lugar consegue usar o mesmo código de novo.
            $this->coupons->release($order);

            return $refund;
        });

        $order->loadMissing('user');
        if (filled($order->user->email)) {
            Mail::to($order->user->email)->queue(new RefundConfirmedMail($order, $reason, amountCents: $amountCents));
        }

        return $refund;
    }
}
