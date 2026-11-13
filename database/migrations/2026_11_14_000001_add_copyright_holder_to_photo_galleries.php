<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titular do direito autoral das fotos (fotógrafo ou escola). Aparece no aviso
 * da marca d'água e nos metadados das prévias. Vazio = padrão do config.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->string('copyright_holder', 80)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->dropColumn('copyright_holder');
        });
    }
};
