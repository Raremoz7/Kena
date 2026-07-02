<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de cada assento numa sessão — a tabela quente do polling.
     * held_by_reservation_id / sold_by_order_id são ponteiros leves (sem FK
     * para evitar dependência circular e atritos com SQLite).
     */
    public function up(): void
    {
        Schema::create('session_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('seat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->string('status')->default('available'); // available | held | sold | blocked
            $table->dateTime('hold_expires_at')->nullable();
            $table->unsignedBigInteger('held_by_reservation_id')->nullable()->index();
            $table->unsignedBigInteger('sold_by_order_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['session_id', 'seat_id']);
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_seats');
    }
};
