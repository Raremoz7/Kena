<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Mail\EventReminderMail;
use App\Mail\RefundConfirmedMail;
use App\Mail\TicketsIssuedMail;
use App\Mail\TicketTransferredMail;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Mail\Mailable;

/**
 * Preview local dos e-mails transacionais — renderiza o HTML de verdade no
 * navegador sem enviar nada. Bloqueado fora de local/testing.
 */
class MailPreviewController extends Controller
{
    public function show(string $type): Mailable
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return match ($type) {
            'tickets-issued' => new TicketsIssuedMail($this->paidOrder()),
            'ticket-transferred' => new TicketTransferredMail($this->anyTicket(), 'Ana Souza'),
            'event-reminder' => new EventReminderMail($this->anyTicket()->session, $this->anyTicket()->user),
            'refund-confirmed' => new RefundConfirmedMail($this->paidOrder()),
            default => abort(404),
        };
    }

    private function paidOrder(): Order
    {
        return Order::query()->where('status', Order::STATUS_PAID)->latest()->first()
            ?? abort(404, 'Nenhum pedido pago no banco — rode "php artisan migrate:fresh --seed" ou faça uma compra de teste.');
    }

    private function anyTicket(): Ticket
    {
        return Ticket::query()->latest()->first()
            ?? abort(404, 'Nenhum ingresso no banco — rode "php artisan migrate:fresh --seed" ou faça uma compra de teste.');
    }
}
