<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cota de cortesia por sessão: só a QUANTIDADE fica guardada, nenhum
        // lugar específico. A venda paga vai até (lugares do mapa − cota).
        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('courtesy_reserve')->default(0)->after('status');
        });

        // Lugar segurado quando a venda paga já tinha esgotado: só fecha com cortesia.
        Schema::table('reservation_seats', function (Blueprint $table): void {
            $table->boolean('requires_courtesy')->default(false)->after('courtesy');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table): void {
            $table->dropColumn('requires_courtesy');
        });

        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->dropColumn('courtesy_reserve');
        });
    }
};
