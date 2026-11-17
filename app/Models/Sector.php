<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $event_id
 * @property string $name
 * @property int $price_cents
 * @property int $position
 * @property-read Event $event
 */
class Sector extends Model
{
    use ScopedThroughParent;

    protected $guarded = [];

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organizationParent(): string
    {
        return 'event';
    }
}
