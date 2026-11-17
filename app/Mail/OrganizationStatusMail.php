<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso ao organizador: vendas suspensas ou reativadas pela plataforma. */
class OrganizationStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organizationName,
        public bool $suspended,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->suspended
            ? "Vendas suspensas: {$this->organizationName}"
            : "Vendas reativadas: {$this->organizationName}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.organization-status',
            with: [
                'organizationName' => $this->organizationName,
                'suspended' => $this->suspended,
                'reason' => $this->reason,
                'panelUrl' => route('painel'),
                'supportEmail' => config('kena.support.email'),
            ],
        );
    }
}
