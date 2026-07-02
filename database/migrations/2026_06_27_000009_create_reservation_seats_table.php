<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_seat_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->timestamps();

            $table->unique(['reservation_id', 'session_seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_seats');
    }
};
