<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quem opera o check-in agora e um `panel_user`, nao um `user` — a FK do
 * operador precisa acompanhar. Os operator_id antigos apontavam para linhas
 * que a migration anterior moveu, entao sao zerados: o historico de check-in
 * fica preservado, so perde de quem foi o scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
        });

        Schema::table('check_ins', function (Blueprint $table) {
            $table->foreignId('operator_id')
                ->nullable()
                ->after('session_id')
                ->constrained('panel_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
        });

        Schema::table('check_ins', function (Blueprint $table) {
            $table->foreignId('operator_id')
                ->nullable()
                ->after('session_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('check_ins')->update(['operator_id' => null]);
    }
};
