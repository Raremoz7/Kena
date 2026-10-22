<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pedido de exemplo, só em memória (nada é salvo), para o e-mail de teste do
 * painel sair igual ao e-mail real de ingressos. Usa o próximo evento cadastrado
 * para o organizador ver o design com dados reais; sem evento, usa um fictício.
 * Os QR codes são inválidos de propósito: não passam no check-in.
 */
final class SampleTicketOrder
{
    public static function make(string $holderName): Order
    {
        $buyer = new User(['name' => $holderName]);

        $order = new Order(['reference' => 'TESTE']);
        $order->setRelation('user', $buyer);
        $order->setRelation('session', self::session());
        $order->setRelation('tickets', new Collection([
            self::ticket('TESTE-0001', 'A10', $holderName),
            self::ticket('TESTE-0002', 'A11', $holderName),
        ]));

        return $order;
    }

    private static function session(): EventSession
    {
        $session = EventSession::query()
            ->with('event.venue')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        if ($session !== null) {
            return $session;
        }

        $venue = new Venue(['name' => 'Teatro de exemplo', 'city' => 'Brasília', 'state' => 'DF']);
        $event = new Event(['title' => 'Espetáculo de exemplo']);
        $event->setRelation('venue', $venue);
        $session = new EventSession(['starts_at' => now()->addWeek()->setTime(20, 0)]);
        $session->setRelation('event', $event);

        return $session;
    }

    private static function ticket(string $code, string $seat, string $holderName): Ticket
    {
        return new Ticket([
            'code' => $code,
            'sector_name' => 'Plateia',
            'seat_code' => $seat,
            'holder_name' => $holderName,
            'qr_token' => 'kena-email-teste-'.strtolower($code),
        ]);
    }
}
