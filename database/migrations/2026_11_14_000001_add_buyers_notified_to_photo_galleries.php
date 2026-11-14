<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quando e para quantas pessoas saiu o aviso "as fotos chegaram". Uma vez por galeria. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->timestamp('buyers_notified_at')->nullable();
            $table->unsignedInteger('buyers_notified_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->dropColumn(['buyers_notified_at', 'buyers_notified_count']);
        });
    }
};
