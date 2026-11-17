<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $code
 * @property string $line
 * @property string $number
 * @property int $pos_x
 * @property int $pos_y
 * @property string $kind
 * @property-read Venue $venue
 */
class Seat extends Model
{
    use ScopedThroughParent;

    protected $guarded = [];

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function organizationParent(): string
    {
        return 'venue';
    }
}
