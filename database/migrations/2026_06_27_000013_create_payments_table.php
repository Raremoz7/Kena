<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('mercadopago');
            $table->string('method'); // card | pix
            $table->string('status')->default('pending'); // pending | approved | rejected | refunded | cancelled
            $table->unsignedInteger('amount_cents');
            $table->string('gateway_payment_id')->nullable()->index();
            $table->text('pix_qr_base64')->nullable();
            $table->text('pix_copy_paste')->nullable();
            $table->dateTime('pix_expires_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
