<?php

namespace App\Services\Photos;

use App\Models\Order;
use App\Models\PhotoGallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quem recebe o aviso de fotos publicadas: as contas com pedido de ingresso
 * pago do evento. Pedido reembolsado ou cancelado não conta, e cada pessoa
 * aparece uma vez, mesmo com vários pedidos ou sessões.
 */
final class PhotoGalleryAudience
{
    /** @return Builder<User> */
    public function query(PhotoGallery $gallery): Builder
    {
        return User::query()
            ->whereNotNull('email')
            ->whereIn('id', Order::query()
                ->ticketOrders()
                ->where('status', Order::STATUS_PAID)
                ->whereHas('session', fn ($q) => $q->where('event_id', $gallery->event_id))
                ->select('user_id'));
    }

    public function count(PhotoGallery $gallery): int
    {
        return $this->query($gallery)->count();
    }
}
