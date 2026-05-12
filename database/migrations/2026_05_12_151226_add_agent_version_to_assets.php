<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega tracking de la versión del agente de inventario PowerShell
 * a cada activo:
 *
 *  - `agent_version`     — versión del .ps1 que envió el último scan
 *                          (ej: "2.0.0"). Permite saber qué PCs todavía
 *                          tienen la versión vieja del agente cuando
 *                          IT publica una nueva.
 *  - `last_scan_status`  — 'ok' | 'partial' | 'error'. Cuando el agente
 *                          falla parcialmente (ej: WMI no respondió) lo
 *                          marca para que IT lo investigue.
 *
 * Ambos se actualizan en cada scan; no rompen los activos creados
 * manualmente en el panel (quedan en null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('agent_version', 20)->nullable()->after('last_scan_at');
            $table->string('last_scan_status', 20)->nullable()->after('agent_version');

            $table->index('agent_version');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['agent_version']);
            $table->dropColumn(['agent_version', 'last_scan_status']);
        });
    }
};
