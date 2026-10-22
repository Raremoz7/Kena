<?php

namespace App\Mail;

use App\Models\EventSession;
use App\Models\User;
use App\Support\MagicLink;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link de acesso para quem já tem conta leve e tentou comprar como convidado.
 * Só quem abre a caixa de entrada continua a compra (ver GuestIdentityService).
 */
class GuestAccessLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public EventSession $session) {}

    public function envelope(): Envelope
    {
        $this->session->loadMissing('event');

        return new Envelope(
            subject: 'Continue sua compra — '.$this->session->event->title,
        );
    }

    public function content(): Content
    {
        $this->session->loadMissing('event.venue');
        $event = $this->session->event;

        return new Content(
            view: 'mail.guest-access-link',
            with: [
                'user' => $this->user,
                'event' => $event,
                'venue' => $event->venue,
                'sessionLabel' => CatalogPresenter::sessionLabel($this->session),
                'ttlMinutes' => MagicLink::CHECKOUT_TTL_MINUTES,
                'accessUrl' => MagicLink::forCheckout(
                    $this->user,
                    route('sessions.seats', ['slug' => $event->slug, 'session' => $this->session->id], false),
                ),
            ],
        );
    }
}
