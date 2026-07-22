<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('registration_source');
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['accepted_by_user_id']);
            $table->dropColumn(['accepted_at', 'accepted_by_user_id']);
        });
    }
};
