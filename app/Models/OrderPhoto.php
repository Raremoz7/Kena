<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de um pedido de fotos: a foto comprada e o preço unitário cobrado
 * (o da faixa alcançada no momento da compra).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $photo_id
 * @property string $filename
 * @property int $unit_price_cents
 * @property-read Order $order
 * @property-read Photo|null $photo
 */
class OrderPhoto extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Photo, $this> */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
