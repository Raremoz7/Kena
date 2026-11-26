<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A organização principal (id 1, com todos os dados de antes do multi-tenant)
 * é a da organizadora que já usa o sistema: ganha o nome e o e-mail da primeira
 * conta de organizador do painel. Só troca o nome provisório; se alguém já
 * renomeou, não mexe. Não apaga nem move nenhum dado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $organization = DB::table('organizations')->where('id', Organization::DEFAULT_ID)->first();
        if ($organization === null || $organization->name !== Organization::DEFAULT_NAME) {
            return;
        }

        $owner = DB::table('panel_users')
            ->where('organization_id', Organization::DEFAULT_ID)
            ->where('role', 'organizer')
            ->orderBy('id')
            ->first();
        if ($owner === null) {
            return;
        }

        $base = Str::slug($owner->name) ?: 'organizador';
        $slug = $base;
        $i = 2;
        while (DB::table('organizations')->where('slug', $slug)->where('id', '!=', Organization::DEFAULT_ID)->exists()) {
            $slug = $base.'-'.$i++;
        }

        DB::table('organizations')->where('id', Organization::DEFAULT_ID)->update([
            'name' => $owner->name,
            'slug' => $slug,
            'contact_email' => $organization->contact_email ?? mb_strtolower($owner->email),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Nome e e-mail ficam: voltar ao provisório não desfaz nada útil.
    }
};
