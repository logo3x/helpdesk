<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kactus_employee_id', 64)->nullable()->unique()->after('identification');
            $table->timestamp('kactus_synced_at')->nullable()->after('kactus_employee_id');
            $table->json('kactus_payload')->nullable()->after('kactus_synced_at');
            $table->enum('employment_status', ['active', 'terminated', 'on_leave'])
                ->default('active')
                ->after('kactus_payload');
            $table->date('hired_at')->nullable()->after('employment_status');
            $table->date('terminated_at')->nullable()->after('hired_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'kactus_employee_id',
                'kactus_synced_at',
                'kactus_payload',
                'employment_status',
                'hired_at',
                'terminated_at',
            ]);
        });
    }
};
