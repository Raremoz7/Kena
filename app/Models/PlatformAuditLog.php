<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro do que o super admin fez em nome de um organizador.
 *
 * @property int $id
 * @property int|null $platform_admin_id
 * @property int|null $organization_id
 * @property string $action
 * @property array<string, mixed>|null $meta
 * @property string|null $ip
 * @property Carbon $created_at
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    /** @param array<string, mixed> $meta */
    public static function record(?PlatformAdmin $admin, string $action, ?int $organizationId = null, array $meta = []): void
    {
        static::create([
            'platform_admin_id' => $admin?->id,
            'organization_id' => $organizationId,
            'action' => $action,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
        ]);
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
