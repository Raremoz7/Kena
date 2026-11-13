<?php

use App\Models\PhotoGallery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acesso à galeria: as fotos mostram rosto e nome de crianças, então só entra
 * quem tem ingresso pago do evento ou o código que a escola passa aos pais.
 *
 * Também guarda os pedidos de quem não quer a criança no filtro por rosto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->string('access_code')->nullable()->unique()->after('status');
        });

        PhotoGallery::query()->whereNull('access_code')->each(
            fn (PhotoGallery $g) => $g->forceFill(['access_code' => PhotoGallery::newAccessCode()])->saveQuietly(),
        );

        // Quem já digitou o código com a conta logada: vale em qualquer aparelho.
        Schema::create('photo_gallery_user', function (Blueprint $table) {
            $table->foreignId('photo_gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['photo_gallery_id', 'user_id']);
        });

        Schema::create('photo_privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guardian_name');
            $table->string('contact');
            $table->string('child_name');
            $table->text('message')->nullable();
            $table->string('status')->default('pending'); // pending | done
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_privacy_requests');
        Schema::dropIfExists('photo_gallery_user');
        Schema::table('photo_galleries', function (Blueprint $table) {
            $table->dropColumn('access_code');
        });
    }
};
