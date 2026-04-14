<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA configuration per department × priority.
 *
 * Each row defines the maximum allowed minutes for first response and
 * resolution for a given combination. The SlaService uses these to compute
 * due dates using business hours (America/Bogota, Mon-Fri 08:00-18:00).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('priority');
            $table->unsignedInteger('first_response_minutes')->comment('Max minutes (business hours) for first response');
            $table->unsignedInteger('resolution_minutes')->comment('Max minutes (business hours) for full resolution');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_configs');
    }
};
