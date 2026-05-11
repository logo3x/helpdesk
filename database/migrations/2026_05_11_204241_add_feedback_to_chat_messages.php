<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas para tracking de la calidad de respuestas del chatbot:
 *
 *  - `source_kind`        — origen de la respuesta del asistente
 *                           (kb_high, kb_medium, flow, llm, fallback, system).
 *  - `kb_article_id`      — ID del artículo KB usado como fuente principal.
 *  - `similarity`         — score de similitud KB cuando aplica (0.0–1.0).
 *  - `helpful`            — feedback del usuario (true=👍, false=👎, null=sin votar).
 *  - `feedback_comment`   — comentario opcional cuando el voto es negativo.
 *  - `feedback_at`        — timestamp del voto.
 *
 * Sin FK fuerte a kb_articles para que el feedback histórico siga siendo
 * válido aunque el artículo se borre. Sí se indexa para joins rápidos
 * en la página de métricas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('source_kind', 20)->nullable()->after('metadata');
            $table->unsignedBigInteger('kb_article_id')->nullable()->after('source_kind');
            $table->decimal('similarity', 5, 4)->nullable()->after('kb_article_id');

            $table->boolean('helpful')->nullable()->after('similarity');
            $table->text('feedback_comment')->nullable()->after('helpful');
            $table->timestamp('feedback_at')->nullable()->after('feedback_comment');

            $table->index('source_kind');
            $table->index('helpful');
            $table->index('kb_article_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['source_kind']);
            $table->dropIndex(['helpful']);
            $table->dropIndex(['kb_article_id']);

            $table->dropColumn([
                'source_kind',
                'kb_article_id',
                'similarity',
                'helpful',
                'feedback_comment',
                'feedback_at',
            ]);
        });
    }
};
