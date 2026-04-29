<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca un comentario como "evento del sistema" (vs comentario humano).
 *
 * Eventos del sistema actuales:
 *   - Traslado entre departamentos (TicketService::transfer)
 *
 * Se pueden agregar a futuro: asignación, resolución, recalibración,
 * reapertura. Cada uno se renderiza en la UI como un divisor de
 * timeline con texto estandarizado en lugar de una burbuja de chat.
 *
 * `event_type` permite distinguir el tipo de evento si se quieren
 * iconos/colores específicos por categoría sin parsear el texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->boolean('is_system_event')->default(false)->after('is_private');
            $table->string('event_type', 40)->nullable()->after('is_system_event');
            $table->index(['ticket_id', 'is_system_event']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'is_system_event']);
            $table->dropColumn(['is_system_event', 'event_type']);
        });
    }
};
