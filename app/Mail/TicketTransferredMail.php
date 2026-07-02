<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso ao novo titular de que recebeu um ingresso por transferência. */
class TicketTransferredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket, public string $fromName) {}

    public function envelope(): Envelope
    {
        $this->ticket->loadMissing('session.event');

        return new Envelope(
            subject: 'Você recebeu um ingresso — '.$this->ticket->session->event->title,
        );
    }

    public function content(): Content
    {
        $this->ticket->loadMissing('session.event.venue');
        $session = $this->ticket->session;

        return new Content(
            view: 'mail.ticket-transferred',
            with: [
                'ticket' => $this->ticket,
                'event' => $session->event,
                'venue' => $session->event->venue,
                'sessionLabel' => CatalogPresenter::sessionLabel($session),
                'fromName' => $this->fromName,
                'ticketsUrl' => TicketsIssuedMail::magicLink($this->ticket->user_id, $session->starts_at),
            ],
        );
    }
}
