<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model com coluna `organization_id`. No painel, consultas só enxergam a
 * organização logada (inclusive o route model binding: item de outra
 * organização vira 404). Ao criar sem organização, assume a do contexto ou,
 * fora do painel, a principal.
 *
 * @property int|null $organization_id
 * @property-read Organization|null $organization
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            $id = CurrentOrganization::id();
            if ($id !== null) {
                $query->where($query->getModel()->qualifyColumn('organization_id'), $id);
            }
        });

        static::creating(function (self $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $model->setAttribute('organization_id', $model->inferOrganizationId()
                    ?? CurrentOrganization::id()
                    ?? Organization::DEFAULT_ID);
            }
        });
    }

    /** Organização deduzida dos próprios dados (ex.: pedido pela sessão). Padrão: nenhuma. */
    protected function inferOrganizationId(): ?int
    {
        return null;
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
