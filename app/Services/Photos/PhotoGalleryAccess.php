<?php

namespace App\Services\Photos;

use App\Models\Order;
use App\Models\PhotoGallery;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Quem pode ver uma galeria de fotos. As fotos mostram rosto e nome de
 * crianças, então o acesso é fechado:
 *
 * - comprador com pedido pago de uma sessão do evento entra direto;
 * - quem digita o código da galeria entra, e fica liberado na sessão do
 *   navegador (e na conta, se estiver logado).
 */
final class PhotoGalleryAccess
{
    private const SESSION_KEY = 'photo_access';

    public function allows(PhotoGallery $gallery, Request $request): bool
    {
        $unlocked = $request->hasSession() ? $request->session()->get(self::SESSION_KEY, []) : [];

        if (in_array($gallery->id, (array) $unlocked, true)) {
            return true;
        }

        $user = $request->user();

        return $user !== null && ($this->hasTicket($gallery, $user) || $this->hasUnlocked($gallery, $user));
    }

    /** Ingresso pago deste evento (pedido reembolsado ou cancelado não vale). */
    public function hasTicket(PhotoGallery $gallery, User $user): bool
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_PAID)
            ->whereHas('session', fn ($q) => $q->where('event_id', $gallery->event_id))
            ->exists();
    }

    public function unlock(PhotoGallery $gallery, Request $request): void
    {
        $request->session()->push(self::SESSION_KEY, $gallery->id);

        if ($request->user() !== null) {
            $gallery->unlockedBy()->syncWithoutDetaching([
                $request->user()->id => ['created_at' => now()],
            ]);
        }
    }

    private function hasUnlocked(PhotoGallery $gallery, User $user): bool
    {
        return $gallery->unlockedBy()->whereKey($user->id)->exists();
    }
}
