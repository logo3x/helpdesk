<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos para el flujo de precarga de personas (Sprint 2, 2026-08-25).
 *
 *  - is_azure_pending: la cuenta se creó desde el import como esperando
 *    su primer login por Azure. Al hacerlo se enlaza (no se crea otra).
 *  - azure_first_login_at: timestamp del enlace exitoso. Sirve de
 *    auditoría.
 *  - password_must_change: forzar cambio en primer login para cuentas
 *    locales (contratistas/operarios con password inicial predecible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_azure_pending')
                ->default(false)
                ->after('azure_id')
                ->comment('Cuenta precargada esperando primer login por Azure.');

            $table->timestamp('azure_first_login_at')
                ->nullable()
                ->after('is_azure_pending')
                ->comment('Timestamp del primer login SSO que enlazó la cuenta.');

            $table->boolean('password_must_change')
                ->default(false)
                ->after('password')
                ->comment('Fuerza cambio de password en el próximo login local.');

            $table->index('is_azure_pending', 'users_is_azure_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_is_azure_pending_idx');
            $table->dropColumn(['is_azure_pending', 'azure_first_login_at', 'password_must_change']);
        });
    }
};
