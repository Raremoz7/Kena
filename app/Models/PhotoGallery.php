<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $event_id
 * @property string $status
 * @property string|null $description
 * @property array<int, array{min_qty: int, unit_price_cents: int}> $price_tiers
 * @property-read Event $event
 */
class PhotoGallery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_tiers' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position');
    }

    /** @return HasMany<PhotoGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(PhotoGroup::class)->orderBy('position');
    }

    /** @return HasMany<PhotoPerson, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(PhotoPerson::class)->orderBy('name');
    }

    /** Faixas em ordem crescente de quantidade. */
    public function sortedTiers(): array
    {
        $tiers = $this->price_tiers;
        usort($tiers, fn ($a, $b) => $a['min_qty'] <=> $b['min_qty']);

        return $tiers;
    }
}
