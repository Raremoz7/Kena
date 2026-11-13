<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoa escondida do filtro por rosto: as fotos continuam na galeria, mas ela
 * não aparece na lista pública (pedido de pais que não querem a filha ali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_people', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('face_path');
        });
    }

    public function down(): void
    {
        Schema::table('photo_people', function (Blueprint $table) {
            $table->dropColumn('hidden');
        });
    }
};
