<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos personales adicionales del usuario que el inventario necesita
 * para llenar las actas de entrega y reportes:
 *
 *   - identification: número de cédula del custodio
 *   - position: cargo (ej: "SOPORTE LOGISTICO Y ADMINISTRATIVO")
 *   - phone: teléfono de contacto
 *
 * Todos nullable porque los users actuales (super_admin, admin de
 * prueba) no tienen estos datos cargados aún.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identification', 30)->nullable()->after('email')
                ->comment('Cédula u otro documento de identidad');
            $table->string('position', 200)->nullable()->after('identification')
                ->comment('Cargo en la empresa');
            $table->string('phone', 30)->nullable()->after('position');

            $table->index('identification');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['identification']);
            $table->dropColumn(['identification', 'position', 'phone']);
        });
    }
};
