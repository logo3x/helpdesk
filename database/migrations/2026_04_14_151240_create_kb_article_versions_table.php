<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->longText('body');
            $table->text('change_summary')->nullable();
            $table->timestamps();
            $table->unique(['kb_article_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_versions');
    }
};
