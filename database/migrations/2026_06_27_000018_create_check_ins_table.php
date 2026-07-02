<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('result'); // ok | denied
            $table->string('reason')->nullable();
            $table->string('scanned_code')->nullable();
            $table->dateTime('scanned_at');
            $table->timestamps();

            $table->index(['session_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
