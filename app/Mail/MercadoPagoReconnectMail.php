<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Aviso ao organizador: a conexão com o Mercado Pago precisa ser refeita. */
class MercadoPagoReconnectMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $organizationName, public ?string $expiresOn = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reconecte o Mercado Pago para continuar vendendo');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.mercadopago-reconnect',
            with: [
                'organizationName' => $this->organizationName,
                'expiresOn' => $this->expiresOn,
                'settingsUrl' => route('admin.settings'),
            ],
        );
    }
}
