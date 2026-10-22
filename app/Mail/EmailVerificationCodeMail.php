<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Código de confirmação do e-mail (checkout de convidado e cadastro).
 * Envio síncrono de propósito: o comprador espera por ele, e o código em claro
 * não fica guardado na fila do banco.
 */
class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $ttlMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->code.' é o seu código Kena',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-verification-code',
            with: [
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
            ],
        );
    }
}
