<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->dateTime('reminded_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('event_sessions', function (Blueprint $table): void {
            $table->dropColumn('reminded_at');
        });
    }
};
