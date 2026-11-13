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
        return new Envelope(subject: 'Pedido cancelado: '.$this->order->event()?->title);
    }

    public function content(): Content
    {
        $this->order->loadMissing('user');
        $event = $this->order->event();
        $photos = $this->order->isPhotos();

        return new Content(
            view: 'mail.order-cancelled',
            with: [
                'order' => $this->order,
                'event' => $event,
                'sessionLabel' => $photos
                    ? 'Fotos do espetáculo'
                    : CatalogPresenter::sessionLabel($this->order->session),
                'reason' => $this->reason,
                'eventsUrl' => $photos && $event !== null
                    ? route('photos.show', $event->slug)
                    : route('events.index'),
                'ctaLabel' => $photos ? 'Voltar às fotos' : 'Ver eventos em cartaz',
            ],
        );
    }
}
