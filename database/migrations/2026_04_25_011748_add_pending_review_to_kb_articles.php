<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow de aprobación de KB articles:
 *
 *   1. Agente crea/edita en Borrador.
 *   2. Agente clic "Solicitar publicación" → marca pending_review_at = now()
 *      y se notifica a los supervisores del depto.
 *   3. Supervisor revisa, clic "Aprobar y publicar" → status='published',
 *      published_at = now(), pending_review_at = null.
 *
 * Sin estas columnas, el supervisor no tendría forma de saber qué
 * borradores están listos para revisar (aparecen mezclados con los
 * que el autor todavía está escribiendo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->timestamp('pending_review_at')->nullable()->after('published_at');
            $table->foreignId('pending_review_by_id')
                ->nullable()
                ->after('pending_review_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropForeign(['pending_review_by_id']);
            $table->dropColumn(['pending_review_at', 'pending_review_by_id']);
        });
    }
};
