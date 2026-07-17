<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas do painel (organizador e staff de check-in), separadas dos
 * compradores em `users`. Guard proprio -> sessao propria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // organizer = gestao completa; staff = so check-in.
            $table->string('role', 20)->default('staff');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_users');
    }
};
