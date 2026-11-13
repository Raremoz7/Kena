<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\MagicLink;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/** Confirmação da compra de fotos, com o link para baixar (enviada no pago). */
class PhotosReadyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** `$preview`: e-mail de teste, mesmo layout sem gerar link de acesso real. */
    public function __construct(public Order $order, public bool $preview = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->preview ? '[Teste] ' : '').'Suas fotos: '.$this->order->event()?->title,
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'photoItems.photo']);
        $count = $this->order->photoItems->count();
        $user = $this->order->user;

        // Só conta sem senha e sem Google (convidado) ganha o link que já entra;
        // as outras entram pelo login normal, como no e-mail dos ingressos.
        $photosUrl = $this->preview || $user->password !== null || $user->google_id !== null
            ? route('photos.mine')
            : MagicLink::forPhotos($user);

        return new Content(
            view: 'mail.photos-ready',
            with: [
                'order' => $this->order,
                'event' => $this->order->event(),
                'countLabel' => $count.' '.($count === 1 ? 'foto' : 'fotos'),
                'amount' => Money::toReais($this->order->total_cents),
                'thumbs' => $this->order->photoItems
                    ->map(fn ($item): ?int => $item->photo?->id)
                    ->filter()
                    ->take(6)
                    // O e-mail não tem a sessão do navegador: link assinado que vale 30 dias.
                    ->map(fn (int $id): string => URL::temporarySignedRoute('photos.image', now()->addDays(30), ['photo' => $id, 'variant' => 'thumb']))
                    ->values()
                    ->all(),
                'photosUrl' => $photosUrl,
            ],
        );
    }
}
