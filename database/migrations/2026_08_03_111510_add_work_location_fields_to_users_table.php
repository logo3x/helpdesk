<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('management_area', 120)->nullable()->after('position');
            $table->string('field', 100)->nullable()->after('management_area');
            $table->string('location_zone', 100)->nullable()->after('field');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['management_area', 'field', 'location_zone']);
        });
    }
};
