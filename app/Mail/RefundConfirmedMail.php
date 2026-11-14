<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Money;
use App\Support\Presenters\CatalogPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmação de reembolso ao comprador — do pedido inteiro ou só de alguns
 * ingressos ($partial), caso em que o e-mail nomeia os assentos devolvidos e
 * deixa claro que os demais continuam valendo.
 */
class RefundConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $seatCodes  assentos reembolsados (vazio = todos do pedido)
     */
    public function __construct(
        public Order $order,
        public ?string $reason = null,
        public ?int $amountCents = null,
        public array $seatCodes = [],
        public bool $partial = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reembolso confirmado: '.$this->order->event()?->title);
    }

    public function content(): Content
    {
        $this->order->loadMissing('user');
        $photos = $this->order->isPhotos();

        return new Content(
            view: 'mail.refund-confirmed',
            with: [
                'order' => $this->order,
                'event' => $this->order->event(),
                'sessionLabel' => $photos
                    ? 'Fotos do espetáculo'
                    : CatalogPresenter::sessionLabel($this->order->session),
                'isPhotos' => $photos,
                'amount' => Money::toReais($this->amountCents ?? $this->order->total_cents),
                'reason' => $this->reason,
                'seatCodes' => $this->seatCodes,
                'partial' => $this->partial,
            ],
        );
    }
}
