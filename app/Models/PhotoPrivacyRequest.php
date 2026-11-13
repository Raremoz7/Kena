<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pedido de um responsável para tirar a criança do filtro por rosto.
 *
 * @property int $id
 * @property int $photo_gallery_id
 * @property int|null $user_id
 * @property string $guardian_name
 * @property string $contact
 * @property string $child_name
 * @property string|null $message
 * @property string $status
 */
class PhotoPrivacyRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    protected $guarded = [];

    /** @return BelongsTo<PhotoGallery, $this> */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(PhotoGallery::class, 'photo_gallery_id');
    }
}
