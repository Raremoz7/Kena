<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pessoa marcada nas fotos (nome dado ao rosto na edição).
 *
 * @property int $id
 * @property int $photo_gallery_id
 * @property string $name
 * @property string|null $face_path
 */
class PhotoPerson extends Model
{
    protected $table = 'photo_people';

    protected $guarded = [];

    /** @return BelongsToMany<Photo, $this> */
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Photo::class, 'photo_person')->withPivot('region');
    }
}
