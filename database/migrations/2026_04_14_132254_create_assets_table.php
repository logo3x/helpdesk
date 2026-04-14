<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 50)->unique()->nullable()->comment('Internal tag like PC-001');
            $table->string('hostname')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('type', 50)->default('desktop')->comment('desktop, laptop, server, printer, other');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('os_name')->nullable();
            $table->string('os_version')->nullable();
            $table->string('os_architecture', 20)->nullable();
            $table->unsignedInteger('cpu_cores')->nullable();
            $table->string('cpu_model')->nullable();
            $table->unsignedInteger('ram_mb')->nullable();
            $table->unsignedBigInteger('disk_total_gb')->nullable();
            $table->string('gpu_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('status', 30)->default('active')->comment('active, maintenance, retired, lost');
            $table->text('notes')->nullable();
            $table->timestamp('last_scan_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('hostname');
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
