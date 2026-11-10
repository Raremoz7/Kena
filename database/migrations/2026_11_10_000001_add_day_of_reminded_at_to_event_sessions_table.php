<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marca quando o lembrete "É hoje" da sessão já foi enviado (só adiciona coluna). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->dateTime('day_of_reminded_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->dropColumn('day_of_reminded_at');
        });
    }
};
