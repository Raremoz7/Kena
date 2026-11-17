<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Link para redefinir a senha do painel. Síncrono de propósito: a pessoa
 * espera por ele, e o token não fica guardado na fila do banco.
 */
class PanelPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $url,
        public int $ttlMinutes,
        /** Convite (conta criada pelo super admin) em vez de "esqueci minha senha". */
        public bool $invite = false,
        public ?string $organizationName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->invite
            ? 'Seu acesso ao painel Kena está pronto'
            : 'Redefinir sua senha do painel Kena');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.panel-password-reset',
            with: [
                'name' => $this->name,
                'url' => $this->url,
                'ttlMinutes' => $this->ttlMinutes,
                'invite' => $this->invite,
                'organizationName' => $this->organizationName,
            ],
        );
    }
}
