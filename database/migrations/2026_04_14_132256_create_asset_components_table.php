<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('type', 50)->comment('cpu, ram, disk, gpu, network, monitor, peripheral');
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->json('specs')->nullable()->comment('Flexible key-value for component-specific details');
            $table->timestamps();

            $table->index(['asset_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_components');
    }
};
