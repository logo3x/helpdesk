<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_software', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('name');
            $table->string('version')->nullable();
            $table->string('publisher')->nullable();
            $table->date('install_date')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_software');
    }
};
