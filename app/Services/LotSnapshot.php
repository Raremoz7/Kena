<?php

namespace App\Services;

use App\Models\TicketLot;
use Illuminate\Database\Eloquent\Collection;

/**
 * Retrato dos lotes de uma sessão num instante: os lotes e quanto cada um já
 * usou. Montado com 3 queries (ver TicketLotService::snapshot) e reaproveitado
 * por tudo que a página precisa — preço, lote atual, saldo, resumo.
 */
final class LotSnapshot
{
    /**
     * @param  Collection<int, TicketLot>  $lots  em ordem de venda
     * @param  array<int, int>  $used  [lotId => ingressos pagos segurados + vendidos]
     */
    public function __construct(
        public readonly Collection $lots,
        private readonly array $used,
    ) {}

    public function isEmpty(): bool
    {
        return $this->lots->isEmpty();
    }

    public function used(TicketLot $lot): int
    {
        return $this->used[$lot->id] ?? 0;
    }

    /** Saldo do lote; null quando o lote não tem limite. */
    public function remaining(TicketLot $lot): ?int
    {
        return $lot->quantity === null ? null : max(0, $lot->quantity - $this->used($lot));
    }

    /** Lote vendendo agora, ou null (sem lotes ou todos esgotados). */
    public function current(): ?TicketLot
    {
        return $this->lots->first(function (TicketLot $lot): bool {
            $left = $this->remaining($lot);

            return $left === null || $left > 0;
        });
    }

    public function soldOut(): bool
    {
        return ! $this->isEmpty() && $this->current() === null;
    }

    /** Preço do ingresso agora: do lote atual; null = usar o preço do setor. */
    public function currentPriceCents(): ?int
    {
        return $this->current()?->price_cents;
    }

    /**
     * Resumo para o painel e para o site.
     *
     * @return array<int, array{id: int, name: string, priceCents: int, quantity: int|null, used: int, remaining: int|null, state: string}>
     */
    public function summary(): array
    {
        $current = $this->current();
        $seenCurrent = false;

        return $this->lots->map(function (TicketLot $lot) use ($current, &$seenCurrent): array {
            // Antes do atual: esgotado. O atual: vendendo. Depois: próximo.
            if ($current !== null && $lot->id === $current->id) {
                $state = 'current';
                $seenCurrent = true;
            } else {
                $state = $seenCurrent ? 'next' : 'sold_out';
            }

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'priceCents' => $lot->price_cents,
                'quantity' => $lot->quantity,
                'used' => $this->used($lot),
                'remaining' => $this->remaining($lot),
                'state' => $state,
            ];
        })->values()->all();
    }
}
