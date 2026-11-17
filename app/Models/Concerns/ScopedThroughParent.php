<?php

namespace App\Models\Concerns;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Model sem `organization_id` próprio que pertence à organização do "pai"
 * (ex.: sessão → evento). No painel, só aparece se o pai estiver visível,
 * e o filtro do pai já é o da organização logada.
 *
 * O model informa a relação com o pai em organizationParent().
 */
trait ScopedThroughParent
{
    /** Nome da relação belongsTo com o "pai" que pertence à organização. */
    abstract public function organizationParent(): string;

    public static function bootScopedThroughParent(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            if (CurrentOrganization::id() !== null) {
                $query->whereHas($query->getModel()->organizationParent());
            }
        });
    }
}
