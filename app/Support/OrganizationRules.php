<?php

namespace App\Support;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Regras de validação que consultam o banco direto (sem o filtro dos models):
 * no painel, restringem à organização logada para um id de outro organizador
 * não passar como válido.
 */
final class OrganizationRules
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $rule = new Exists($table, $column);
        $id = CurrentOrganization::id();

        return $id !== null ? $rule->where('organization_id', $id) : $rule;
    }

    public static function unique(string $table, string $column): Unique
    {
        $rule = new Unique($table, $column);
        $id = CurrentOrganization::id();

        return $id !== null ? $rule->where('organization_id', $id) : $rule;
    }
}
