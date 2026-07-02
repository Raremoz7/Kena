<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('kicker');
            $table->text('description');
            $table->string('status')->default('on_sale'); // on_sale | sold_out | draft | closed
            $table->string('duration_label')->nullable();
            $table->string('banner_from')->default('oklch(0.32 0.08 285)');
            $table->string('banner_to')->default('oklch(0.14 0.012 48)');
            $table->string('banner_image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
