<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Atalhos antigos do painel (oklch) → hex, para o seletor de cor do navegador. */
    private const PRESETS = [
        'oklch(0.32 0.08 285)' => '#2f2b59',
        'oklch(0.3 0.08 22)' => '#4f1a1b',
        'oklch(0.26 0.05 250)' => '#0f253b',
        'oklch(0.27 0.06 160)' => '#012f1c',
        'oklch(0.14 0.012 48)' => '#0d0806',
    ];

    public function up(): void
    {
        // Opacidade (0–100) do degradê por cima da imagem do banner. 0 = visual de antes.
        Schema::table('events', function (Blueprint $table): void {
            $table->unsignedTinyInteger('banner_opacity')->default(0)->after('banner_to');
        });

        foreach (self::PRESETS as $oklch => $hex) {
            DB::table('events')->where('banner_from', $oklch)->update(['banner_from' => $hex]);
            DB::table('events')->where('banner_to', $oklch)->update(['banner_to' => $hex]);
        }
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('banner_opacity');
        });

        foreach (self::PRESETS as $oklch => $hex) {
            DB::table('events')->where('banner_from', $hex)->update(['banner_from' => $oklch]);
            DB::table('events')->where('banner_to', $hex)->update(['banner_to' => $oklch]);
        }
    }
};
