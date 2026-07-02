<?php

namespace App\Mail;

use App\Models\EventSession;
use App\Models\User;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Lembrete véspera (D-1) para os titulares de ingresso de uma sessão. */
class EventReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public EventSession $session, public User $user) {}

    public function envelope(): Envelope
    {
        $this->session->loadMissing('event');

        return new Envelope(subject: 'É amanhã: '.$this->session->event->title);
    }

    public function content(): Content
    {
        $this->session->loadMissing('event.venue');
        $event = $this->session->event;

        return new Content(
            view: 'mail.event-reminder',
            with: [
                'name' => $this->user->name,
                'event' => $event,
                'venue' => $event->venue,
                'sessionLabel' => CatalogPresenter::sessionLabel($this->session),
                'ticketsUrl' => TicketsIssuedMail::magicLink($this->user->id, $this->session->starts_at),
            ],
        );
    }
}
