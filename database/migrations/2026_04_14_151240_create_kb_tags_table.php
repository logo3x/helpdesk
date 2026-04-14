<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('kb_article_tag', function (Blueprint $table) {
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('kb_tag_id')->constrained('kb_tags')->cascadeOnDelete();
            $table->primary(['kb_article_id', 'kb_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_tag');
        Schema::dropIfExists('kb_tags');
    }
};
