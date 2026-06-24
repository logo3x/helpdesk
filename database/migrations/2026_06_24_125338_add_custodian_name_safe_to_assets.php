<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotente: agrega custodian_name solo si no existe.
        // La migración original (2026_06_24_105642) pudo no haberse aplicado
        // en producción si after-deploy.bat falló silenciosamente ese día.
        if (Schema::hasColumn('assets', 'custodian_name')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table) {
            $table->string('custodian_name', 150)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'custodian_name')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('custodian_name');
            });
        }
    }
};
