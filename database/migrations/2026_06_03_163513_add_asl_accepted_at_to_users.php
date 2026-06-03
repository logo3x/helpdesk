<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acuerdo de Servicio (ASL): timestamp en el que el usuario aceptó
 * los términos de uso. Si es null, el middleware EnsureAslAccepted
 * lo redirige a /asl/accept antes de servir cualquier panel.
 *
 * Idempotente para reruns en prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'asl_accepted_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('asl_accepted_at')->nullable()->after('last_login_ip');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'asl_accepted_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('asl_accepted_at');
            });
        }
    }
};
