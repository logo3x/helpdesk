<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite jerarquía padre → hija (un solo nivel) dentro de cada
 * departamento. Útil para que, por ejemplo, "Soporte técnico" tenga
 * sub-categorías como "Impresoras", "VPN", "Correo".
 *
 * Reglas aplicadas en el form (no en BD): solo 2 niveles de
 * profundidad. parent_id null = raíz. parent_id != null => es hija.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'parent_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('department_id')
                ->constrained('categories')
                ->nullOnDelete();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'parent_id')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'sort_order']);
            $table->dropColumn('parent_id');
        });
    }
};
