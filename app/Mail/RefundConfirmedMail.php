<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmação de reembolso ao comprador. */
class RefundConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $this->order->loadMissing('session.event');

        return new Envelope(subject: 'Reembolso confirmado — '.$this->order->session->event->title);
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'session.event']);
        $session = $this->order->session;

        return new Content(
            view: 'mail.refund-confirmed',
            with: [
                'order' => $this->order,
                'event' => $session->event,
                'sessionLabel' => CatalogPresenter::sessionLabel($session),
                'amount' => Money::toReais($this->order->total_cents),
            ],
        );
    }
}
