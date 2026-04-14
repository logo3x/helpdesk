<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flow_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_flow_id')->constrained('chat_flows')->cascadeOnDelete();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->unsignedTinyInteger('steps_completed')->default(0);
            $table->boolean('escalated')->default(false);
            $table->timestamps();
            $table->index('chat_flow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_stats');
    }
};
