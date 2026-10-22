<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lotes de venda por sessão: cada um vende até `quantity` ingressos pagos
        // pelo seu preço; esgotou, o próximo (por `position`) abre sozinho.
        Schema::create('ticket_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('quantity')->nullable(); // null = sem limite (até acabar o mapa)
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['session_id', 'position']);
        });

        // Em que lote cada assento entrou, e se saiu como cortesia (não conta no lote).
        Schema::table('reservation_seats', function (Blueprint $table): void {
            $table->foreignId('lot_id')->nullable()->after('price_cents')->constrained('ticket_lots')->nullOnDelete();
            $table->boolean('courtesy')->default(false)->after('lot_id');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('lot_id')->nullable()->after('price_cents')->constrained('ticket_lots')->nullOnDelete();
            $table->boolean('courtesy')->default(false)->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lot_id');
            $table->dropColumn('courtesy');
        });

        Schema::table('reservation_seats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lot_id');
            $table->dropColumn('courtesy');
        });

        Schema::dropIfExists('ticket_lots');
    }
};
