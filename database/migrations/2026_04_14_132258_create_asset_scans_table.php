<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('source', 20)->comment('web_scan or agent_scan');
            $table->json('raw_data')->comment('Full scan payload as received');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_scans');
    }
};
