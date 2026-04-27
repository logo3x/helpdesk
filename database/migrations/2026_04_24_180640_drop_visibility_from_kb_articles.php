<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se elimina la columna `visibility` porque quedó redundante con `status`:
 *
 * - Los únicos canales donde aparecen los KB articles son /soporte
 *   (solo staff, sin filtro de visibilidad) y el chatbot vía RagService
 *   (que ya filtra por `status = published`).
 * - No existe un resource de KB en /portal; cuando exista, también
 *   se filtrará por `status = published`.
 * - El scope `scopePubliclyVisible()` no se llamaba desde ningún lado.
 *
 * Con este cambio el flujo queda: un agente crea en Borrador → un
 * supervisor lo aprueba pasándolo a Publicado → automáticamente es
 * visible en el chatbot (y en /portal cuando se agregue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            // MySQL requiere que el índice compuesto se elimine antes
            // de dropear la columna a la que referencia.
            $table->dropIndex(['status', 'visibility']);
            $table->dropColumn('visibility');
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            // Reemplazamos con un índice simple sobre status, que es
            // el filtro que usan RagService y los widgets.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->string('visibility', 30)->default('internal')->after('status');
        });

        Schema::table('kb_articles', function (Blueprint $table) {
            $table->index(['status', 'visibility']);
        });
    }
};
