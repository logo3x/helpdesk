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
        // Implementación portable MySQL / SQLite (para test suite).
        foreach (['serial_number', 'sap_code'] as $col) {
            $this->nullifyDuplicates($col);
        }

        Schema::table('assets', function (Blueprint $table) use (&$exists) {
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

    /**
     * Nullifica el valor de $col en todos los assets duplicados
     * excepto el de menor id (el más antiguo). Portable MySQL/SQLite.
     */
    private function nullifyDuplicates(string $col): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
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

            return;
        }

        // SQLite (y otros): en lugar de UPDATE con JOIN, hacemos un
        // subquery en el WHERE. Para cada valor duplicado, buscamos
        // el MIN(id) y anulamos los demás.
        DB::statement("
            UPDATE assets
            SET {$col} = NULL
            WHERE {$col} IS NOT NULL
              AND {$col} != ''
              AND id NOT IN (
                  SELECT MIN(id)
                  FROM assets
                  WHERE {$col} IS NOT NULL AND {$col} != ''
                  GROUP BY {$col}
              )
              AND {$col} IN (
                  SELECT {$col}
                  FROM assets
                  WHERE {$col} IS NOT NULL AND {$col} != ''
                  GROUP BY {$col}
                  HAVING COUNT(*) > 1
              )
        ");
    }

    /**
     * hasIndex portable — MySQL usa SHOW INDEX, SQLite pragma_index_list.
     */
    private function hasIndex(string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return ! empty(DB::select(
                'SHOW INDEX FROM `assets` WHERE Key_name = ?',
                [$indexName]
            ));
        }

        // SQLite: pragma_index_list devuelve todos los índices de la tabla.
        $rows = DB::select("SELECT name FROM pragma_index_list('assets') WHERE name = ?", [$indexName]);

        return ! empty($rows);
    }
};
