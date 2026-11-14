<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galeria de fotos à venda por evento.
 *
 * Os originais em alta ficam fora do servidor (Google Drive); aqui ficam só a
 * referência a eles, as versões leves (prévia com marca d'água e miniatura) e
 * quem aparece em cada foto, lido dos metadados que o Lightroom grava.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | published
            $table->text('description')->nullable();
            // Faixas de preço: [{"min_qty": 1, "unit_price_cents": 3500}, ...]
            $table->json('price_tiers');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Turma, coreografia ou "Estúdio": o segundo filtro, para quando o rosto não foi marcado.
        Schema::create('photo_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_gallery_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('photo_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_gallery_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Miniatura do rosto recortada de uma das fotos (a de maior rosto).
            $table->string('face_path')->nullable();
            $table->timestamps();

            $table->unique(['photo_gallery_id', 'name']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_gallery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            // Onde está o original em alta (ex.: id do arquivo no Google Drive).
            $table->string('original_ref')->nullable();
            $table->string('preview_path');
            $table->string('thumb_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('photo_person', function (Blueprint $table) {
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_person_id')->constrained('photo_people')->cascadeOnDelete();
            // Área do rosto no padrão MWG (o mesmo que o Lightroom grava no XMP):
            // centro x/y, largura e altura, normalizados de 0 a 1.
            $table->json('region')->nullable();

            $table->primary(['photo_id', 'photo_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_person');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('photo_people');
        Schema::dropIfExists('photo_groups');
        Schema::dropIfExists('photo_galleries');
    }
};
