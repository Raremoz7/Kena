<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use App\Support\MagicLink;
use App\Support\Presenters\CatalogPresenter;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirmação de compra com os ingressos + QR (enviada ao comprador no pago). */
class TicketsIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $this->order->loadMissing('session.event');

        return new Envelope(
            subject: 'Seus ingressos — '.$this->order->session->event->title,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['tickets', 'user', 'session.event.venue']);
        $session = $this->order->session;

        return new Content(
            view: 'mail.tickets-issued',
            with: [
                'order' => $this->order,
                'event' => $session->event,
                'venue' => $session->event->venue,
                'sessionLabel' => CatalogPresenter::sessionLabel($session),
                'ticketsUrl' => self::magicLink($this->order->user_id, $session->starts_at),
            ],
        );
    }

    /** Magic-link de uso único (rotaciona o anterior) — ver App\Support\MagicLink. */
    public static function magicLink(int $userId, CarbonInterface $sessionStart): string
    {
        return MagicLink::generate(User::findOrFail($userId), $sessionStart);
    }
}
