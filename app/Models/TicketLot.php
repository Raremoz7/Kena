<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lote de venda de uma sessão. Vende até `quantity` ingressos pagos pelo seu
 * preço; esgotado, o próximo por `position` abre sozinho. Cortesias não contam.
 *
 * @property int $id
 * @property int $session_id
 * @property string $name
 * @property int $price_cents
 * @property int|null $quantity
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EventSession $session
 */
class TicketLot extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<EventSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'session_id');
    }
}
