<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $photo_gallery_id
 * @property int|null $photo_group_id
 * @property string $filename
 * @property string|null $original_ref
 * @property string $preview_path
 * @property string $thumb_path
 * @property int $width
 * @property int $height
 */
class Photo extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<PhotoGallery, $this> */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(PhotoGallery::class, 'photo_gallery_id');
    }

    /** @return BelongsTo<PhotoGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PhotoGroup::class, 'photo_group_id');
    }

    /** @return BelongsToMany<PhotoPerson, $this> */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(PhotoPerson::class, 'photo_person')->withPivot('region');
    }
}
