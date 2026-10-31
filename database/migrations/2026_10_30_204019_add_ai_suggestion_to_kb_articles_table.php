<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca los artículos KB que propuso la IA a partir de consultas del
     * chatbot sin respuesta, y guarda por qué se sugirieron.
     */
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->boolean('is_ai_suggestion')->default(false)->after('status')->index();
            $table->json('suggestion_context')->nullable()->after('is_ai_suggestion');
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropColumn(['is_ai_suggestion', 'suggestion_context']);
        });
    }
};
