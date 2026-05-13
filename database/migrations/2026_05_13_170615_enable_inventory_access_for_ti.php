<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marca al departamento de TI con `can_access_inventory = true`.
 *
 * El flag se introdujo en la migración 2026_04_25_211524 con default false
 * para todos los departamentos. Es razonable que TI vea inventario por
 * defecto (es quien lo administra), así no hay que hacer click manual
 * en cada instalación.
 *
 * Idempotente: si TI no existe, no hace nada. Si ya está en true, no
 * cambia nada. Si está en false o null, lo pone en true.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('slug', 'ti')
            ->update(['can_access_inventory' => true]);
    }

    public function down(): void
    {
        DB::table('departments')
            ->where('slug', 'ti')
            ->update(['can_access_inventory' => false]);
    }
};
