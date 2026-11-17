<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso ao super admin: um organizador novo se cadastrou. */
class NewOrganizerSignupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organizationName,
        public string $ownerName,
        public string $ownerEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Novo organizador no Kena: {$this->organizationName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.new-organizer-signup',
            with: [
                'organizationName' => $this->organizationName,
                'ownerName' => $this->ownerName,
                'ownerEmail' => $this->ownerEmail,
                'adminUrl' => route('super.organizations'),
            ],
        );
    }
}
