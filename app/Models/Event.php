<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $slug
 * @property string $title
 * @property string $kicker
 * @property string $description
 * @property string $status
 * @property string|null $duration_label
 * @property string $banner_from
 * @property string $banner_to
 * @property string|null $banner_image
 * @property-read Venue $venue
 */
class Event extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<Sector, $this> */
    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class)->orderBy('position');
    }

    /** @return HasMany<EventSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('starts_at');
    }
}
