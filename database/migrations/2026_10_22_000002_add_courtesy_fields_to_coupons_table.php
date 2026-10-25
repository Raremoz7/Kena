<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cortesia nominal: para quem é (ex.: a bailarina) e de qual grupo (ex.: a escola).
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('beneficiary', 120)->nullable()->after('code');
            $table->string('group_name', 80)->nullable()->after('beneficiary');
            $table->index('group_name');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropIndex(['group_name']);
            $table->dropColumn(['beneficiary', 'group_name']);
        });
    }
};
