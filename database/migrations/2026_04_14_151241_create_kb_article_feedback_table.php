<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['kb_article_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_feedback');
    }
};
