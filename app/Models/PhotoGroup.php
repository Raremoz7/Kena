<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $photo_gallery_id
 * @property string $name
 * @property int $position
 */
class PhotoGroup extends Model
{
    protected $guarded = [];
}
