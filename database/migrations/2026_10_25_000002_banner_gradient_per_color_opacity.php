<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O degradê do banner vira a faixa horizontal por cima da foto no topo do
     * evento (antes fixa no código), com opacidade por cor. Todos os eventos
     * partem do visual que já tinham: escuro 94% → escuro 35%.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedTinyInteger('banner_from_opacity')->default(94)->after('banner_from');
            $table->unsignedTinyInteger('banner_to_opacity')->default(35)->after('banner_to');
        });

        DB::table('events')->update([
            'banner_from' => '#0d0806',
            'banner_to' => '#0d0806',
            'banner_from_opacity' => 94,
            'banner_to_opacity' => 35,
        ]);

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('banner_opacity');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedTinyInteger('banner_opacity')->default(0)->after('banner_to');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['banner_from_opacity', 'banner_to_opacity']);
        });
    }
};
