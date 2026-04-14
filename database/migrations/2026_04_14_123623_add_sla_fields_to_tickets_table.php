<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('sla_config_id')->nullable()->after('category_id')
                ->constrained('sla_configs')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->after('reopened_at');
            $table->timestamp('resolution_due_at')->nullable()->after('first_response_due_at');
            $table->boolean('first_response_breached')->default(false)->after('resolution_due_at');
            $table->boolean('resolution_breached')->default(false)->after('first_response_breached');
            $table->unsignedInteger('paused_minutes')->default(0)->after('resolution_breached')
                ->comment('Total business minutes paused (pendiente_cliente)');
            $table->timestamp('paused_at')->nullable()->after('paused_minutes')
                ->comment('When the ticket entered pendiente_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sla_config_id');
            $table->dropColumn([
                'first_response_due_at',
                'resolution_due_at',
                'first_response_breached',
                'resolution_breached',
                'paused_minutes',
                'paused_at',
            ]);
        });
    }
};
