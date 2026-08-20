<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla que soporta el módulo "Mantenimientos Programados" del panel
 * de soporte. Cada fila es una programación individual de mantenimiento
 * para un activo (solo desktops, laptops, all-in-ones y servers).
 *
 * Al cerrar un mantenimiento (status='cumplido') el observer:
 *   1. Escribe un AssetHistory con action='maintenance' + observaciones.
 *   2. Actualiza assets.last_maintenance_at + maintenance_interval_days.
 *   3. Auto-genera la siguiente ocurrencia (parent_id apunta a ésta),
 *      con scheduled_at = scheduled_at original + días de la frecuencia.
 *
 * Al marcar 'no_cumplido' pide not_completed_reason y también auto-genera
 * la siguiente ocurrencia bajo la misma regla (fecha original + freq).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_maintenances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('scheduled_maintenances')->nullOnDelete();

            $table->date('scheduled_at');
            $table->enum('status', ['pendiente', 'cumplido', 'no_cumplido'])->default('pendiente');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->enum('frequency', ['cuatrimestral', 'anual']);
            $table->text('observations')->nullable();
            $table->text('not_completed_reason')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Guardan la primera vez que el sistema disparó cada tipo de
            // notificación para no re-notificar la misma cosa al agente.
            $table->timestamp('notified_agent_at')->nullable();
            $table->timestamp('notified_due_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index('agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_maintenances');
    }
};
