<?php

namespace App\Models;

use App\Models\Concerns\ScopedThroughParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $photo_gallery_id
 * @property string $name
 * @property int $position
 */
class PhotoGroup extends Model
{
    use ScopedThroughParent;

    protected $guarded = [];

    public function organizationParent(): string
    {
        return 'gallery';
    }

    /** @return BelongsTo<PhotoGallery, $this> */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(PhotoGallery::class, 'photo_gallery_id');
    }
}
