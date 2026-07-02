<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_seat_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();      // KNA-…
            $table->string('qr_token')->unique();  // assinado (HMAC)
            $table->string('holder_name');
            $table->string('seat_code');
            $table->string('sector_name');
            $table->unsignedInteger('price_cents');
            $table->string('status')->default('valid'); // valid | used | transferred | refunded | cancelled
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
