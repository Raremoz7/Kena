<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapa físico do venue: cada assento posicionado por coordenada (x/y).
     * Independe de sessão — a disponibilidade vive em session_seats.
     */
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('code');          // "A10"
            $table->string('line');          // "A", "CAD"
            $table->string('number');        // "10" (string p/ casos como cadeirante)
            $table->unsignedInteger('pos_x');
            $table->unsignedInteger('pos_y');
            $table->string('kind')->default('standard'); // standard | accessible | companion
            $table->timestamps();

            $table->unique(['venue_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
