<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas do Mercado Pago conectadas por OAuth. Hoje existe um organizador só,
 * então vale a última conta conectada; a tabela já comporta várias (multi-tenant).
 * Pagamentos guardam a conta que os criou: estorno e consulta usam o token certo
 * mesmo depois de o organizador trocar de conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercadopago_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('mp_user_id')->unique();
            $table->string('nickname')->nullable();
            $table->text('access_token'); // encriptado (cast)
            $table->text('refresh_token')->nullable(); // encriptado (cast)
            $table->string('public_key')->nullable();
            $table->string('scope')->nullable();
            $table->boolean('live_mode')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('panel_users')->nullOnDelete();
            $table->dateTime('connected_at');
            $table->dateTime('disconnected_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('mercadopago_account_id')->nullable()->after('gateway')
                ->constrained('mercadopago_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mercadopago_account_id');
        });

        Schema::dropIfExists('mercadopago_accounts');
    }
};
