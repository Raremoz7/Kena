<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso de pedido cancelado sem cobrança (Pix expirado, sessão cancelada etc.). */
class OrderCancelledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        $this->order->loadMissing('session.event');

        return new Envelope(subject: 'Pedido cancelado — '.$this->order->session->event->title);
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'session.event']);
        $session = $this->order->session;

        return new Content(
            view: 'mail.order-cancelled',
            with: [
                'order' => $this->order,
                'event' => $session->event,
                'sessionLabel' => CatalogPresenter::sessionLabel($session),
                'reason' => $this->reason,
                'eventsUrl' => route('events.index'),
            ],
        );
    }
}
