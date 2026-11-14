<?php

namespace App\Mail;

use App\Models\PhotoGallery;
use App\Models\User;
use App\Support\MagicLink;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Aviso de que as fotos do espetáculo estão no ar, para quem comprou ingresso.
 * Sai uma vez por galeria, disparado pelo painel (nunca automático ao publicar).
 */
class PhotoGalleryPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Fotos que aparecem no e-mail, como vitrine. */
    private const COVERS = 3;

    /** `$preview`: e-mail de teste, mesmo layout sem gerar link de acesso real. */
    public function __construct(public PhotoGallery $gallery, public User $user, public bool $preview = false) {}

    public function envelope(): Envelope
    {
        $this->gallery->loadMissing('event');

        return new Envelope(
            subject: ($this->preview ? '[Teste] ' : '').'As fotos de '.$this->gallery->event->title.' chegaram',
        );
    }

    public function content(): Content
    {
        $gallery = $this->gallery->loadMissing('event');
        $event = $gallery->event;
        $path = '/fotos/'.$event->slug;
        $tiers = $gallery->sortedTiers();
        $first = $tiers[0] ?? null;
        $best = count($tiers) > 1 ? $tiers[count($tiers) - 1] : null;

        return new Content(
            view: 'mail.photo-gallery-published',
            with: [
                'firstName' => trim(strtok((string) $this->user->name, ' ') ?: ''),
                'eventTitle' => $event->title,
                'photoCount' => $gallery->photos()->count(),
                'peopleCount' => $gallery->people()->where('hidden', false)->count(),
                // Fotos com mais gente primeiro: mostram o palco cheio, não um detalhe.
                'covers' => $gallery->photos()
                    ->withCount('people')
                    ->reorder()
                    ->orderByDesc('people_count')
                    ->orderBy('position')
                    ->limit(self::COVERS)
                    ->pluck('id')
                    // O e-mail não tem a sessão do navegador: link assinado que vale 30 dias.
                    ->map(fn (int $id): string => URL::temporarySignedRoute('photos.image', now()->addDays(30), ['photo' => $id, 'variant' => 'thumb']))
                    ->all(),
                'unitPrice' => $first ? self::reais((int) $first['unit_price_cents']) : null,
                'bestQty' => $best ? (int) $best['min_qty'] : null,
                'bestPrice' => $best ? self::reais((int) $best['unit_price_cents']) : null,
                'galleryUrl' => $this->preview ? url($path) : MagicLink::forGallery($this->user, $path),
                'accessCode' => $gallery->access_code,
            ],
        );
    }

    /** "35" ou "22,50": centavo só aparece quando existe. */
    private static function reais(int $cents): string
    {
        $value = Money::toReais($cents);

        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0, ',', '.')
            : number_format($value, 2, ',', '.');
    }
}
