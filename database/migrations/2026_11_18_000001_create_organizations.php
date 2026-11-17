<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-tenant: cada organizador de eventos é uma organização. Tudo o que já
 * existe vira a "Organização principal" (id 1), então nada muda para quem já usa.
 * O super admin (Somo) tem conta própria em platform_admins, fora de qualquer
 * organização, e entra pelo /admin. Cada "entrar como" fica no registro.
 */
return new class extends Migration
{
    /** Tabelas que pertencem diretamente a uma organização. */
    private const OWNED = ['events', 'venues', 'coupons', 'orders', 'panel_users', 'mercadopago_accounts'];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('document', 20)->nullable(); // CPF ou CNPJ
            $table->string('contact_email')->nullable();
            $table->string('status', 20)->default('active'); // active | suspended
            $table->dateTime('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();
            // Null = taxa padrão da plataforma (config kena.service_fee_percent).
            $table->decimal('service_fee_percent', 5, 2)->nullable();
            // Taxa das fotos, independente da do ingresso. Null = padrão (kena.photo_fee_percent).
            $table->decimal('photo_fee_percent', 5, 2)->nullable();
            // Null = valor global (painel da plataforma ou .env).
            $table->string('statement_descriptor', 60)->nullable();
            $table->unsignedSmallInteger('pix_expiration_minutes')->nullable();
            $table->dateTime('terms_accepted_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('organizations')->insert([
            'id' => 1,
            'name' => 'Organização principal',
            'slug' => 'principal',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (self::OWNED as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->foreignId('organization_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });
            DB::table($table)->update(['organization_id' => 1]);
        }

        // Código de cupom passa a ser único por organização (dois organizadores
        // podem ter um "PROMO10").
        Schema::table('coupons', function (Blueprint $t): void {
            $t->dropUnique(['code']);
            $t->unique(['organization_id', 'code']);
        });

        Schema::table('panel_users', function (Blueprint $t): void {
            $t->timestamp('email_verified_at')->nullable()->after('email');
        });
        DB::table('panel_users')->update(['email_verified_at' => $now]);

        // Recuperação de senha do painel: tabela própria (o comprador usa
        // password_reset_tokens; o mesmo e-mail pode existir nos dois).
        Schema::create('panel_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Super admin: conta separada, guard próprio (admin).
        Schema::create('platform_admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Registro do que o super admin faz em nome de um organizador.
        Schema::create('platform_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_password_reset_tokens');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_admins');

        Schema::table('panel_users', function (Blueprint $t): void {
            $t->dropColumn('email_verified_at');
        });

        // No MySQL o índice único (organization_id, code) sustenta a chave
        // estrangeira: solta a FK antes de trocar o índice.
        Schema::table('coupons', function (Blueprint $t): void {
            $t->dropForeign(['organization_id']);
        });
        Schema::table('coupons', function (Blueprint $t): void {
            $t->dropUnique(['organization_id', 'code']);
            $t->unique(['code']);
            $t->dropColumn('organization_id');
        });

        foreach (array_reverse(self::OWNED) as $table) {
            if ($table === 'coupons') {
                continue;
            }
            Schema::table($table, function (Blueprint $t): void {
                $t->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::dropIfExists('organizations');
    }
};
