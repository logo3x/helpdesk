<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('type')->comment('first_response_breach, resolution_breach, warning_70, warning_90');
            $table->unsignedInteger('sla_minutes')->comment('The SLA limit that was breached/warned');
            $table->unsignedInteger('elapsed_minutes')->comment('Business minutes elapsed at the time of escalation');
            $table->foreignId('notified_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_logs');
    }
};
