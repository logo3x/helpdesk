<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores vector embeddings for KB article chunks.
 *
 * For MVP (<1000 articles) we store the vector as JSON and compute
 * cosine similarity in PHP. For scale, migrate to pgvector or a
 * dedicated vector DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0)->comment('0 = full article, 1+ = paragraph chunks');
            $table->text('content')->comment('The text chunk that was embedded');
            $table->json('embedding')->comment('Float vector as JSON array');
            $table->unsignedSmallInteger('dimensions')->default(0);
            $table->timestamps();

            $table->index(['kb_article_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_embeddings');
    }
};
