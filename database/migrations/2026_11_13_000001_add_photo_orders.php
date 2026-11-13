<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venda de fotos no mesmo pedido dos ingressos: `orders` ganha o tipo
 * (tickets | photos) e a galeria, e a sessão passa a ser opcional (foto é do
 * evento, não de uma sessão). Os itens de foto ficam em `order_photos`.
 * Pagamento, webhook, conciliação e estorno seguem os mesmos de sempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('kind')->default('tickets')->after('id');
            $table->foreignId('photo_gallery_id')->nullable()->after('session_id')
                ->constrained()->nullOnDelete();
            $table->foreignId('session_id')->nullable()->change();

            $table->index(['kind', 'status']);
        });

        Schema::create('order_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Foto apagada da galeria depois da venda: o item continua no
            // histórico do pedido (com o nome do arquivo), só sem o download.
            $table->foreignId('photo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->unsignedInteger('unit_price_cents');
            $table->timestamps();

            $table->unique(['order_id', 'photo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_photos');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'status']);
            $table->dropConstrainedForeignId('photo_gallery_id');
            $table->dropColumn('kind');
        });
    }
};
