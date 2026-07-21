<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Antes de crear el índice único, nullificar duplicados manteniendo
        // solo el activo con id más bajo (el registro más antiguo).
        foreach (['serial_number', 'sap_code'] as $col) {
            DB::statement("
                UPDATE assets a
                JOIN (
                    SELECT MIN(id) AS keep_id, `{$col}`
                    FROM assets
                    WHERE `{$col}` IS NOT NULL AND `{$col}` != ''
                    GROUP BY `{$col}`
                    HAVING COUNT(*) > 1
                ) dup ON a.`{$col}` = dup.`{$col}` AND a.id != dup.keep_id
                SET a.`{$col}` = NULL
            ");
        }

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
