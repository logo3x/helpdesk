<?php

use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que el admin habilite el módulo de Inventario por
 * departamento. Solo los miembros de un depto con la bandera
 * activa verán el item "Inventario" en /soporte.
 *
 * Por defecto false: la migración tampoco activa nada
 * automáticamente — el admin debe entrar a Configuración →
 * Departamentos y marcar el toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('can_access_inventory')->default(false)->after('is_active');
        });

        // Migración de datos: el depto de TI (slug=ti) recibe acceso
        // por defecto, pues es el caso de uso evidente del módulo.
        // Si no existe, no hace nada.
        Department::where('slug', 'ti')->update(['can_access_inventory' => true]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('can_access_inventory');
        });
    }
};
