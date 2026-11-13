<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasta do Google Drive de onde a galeria importa as fotos e o resultado da
 * última importação (feita numa fila, então o painel lê o estado daqui).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->string('drive_folder')->nullable();
            $table->string('import_status')->nullable(); // queued | running | done | failed
            $table->json('import_report')->nullable();
            $table->timestamp('import_finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->dropColumn(['drive_folder', 'import_status', 'import_report', 'import_finished_at']);
        });
    }
};
