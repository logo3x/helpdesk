<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! $this->hasIndex('assets_serial_number_unique')) {
                $table->unique('serial_number');
            }
            if (! $this->hasIndex('assets_sap_code_unique')) {
                $table->unique('sap_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropUnique(['sap_code']);
        });
    }

    private function hasIndex(string $indexName): bool
    {
        return ! empty(DB::select(
            'SHOW INDEX FROM `assets` WHERE Key_name = ?',
            [$indexName]
        ));
    }
};
