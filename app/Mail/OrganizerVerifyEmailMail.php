<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Link de confirmação do e-mail de quem criou uma conta de organizador. */
class OrganizerVerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $url, public int $ttlHours) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirme seu e-mail para vender no Kena');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.organizer-verify-email',
            with: ['name' => $this->name, 'url' => $this->url, 'ttlHours' => $this->ttlHours],
        );
    }
}
