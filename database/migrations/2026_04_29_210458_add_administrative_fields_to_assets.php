<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía la tabla `assets` con todos los campos administrativos y de
 * mantenimiento que IT Confipetrol maneja hoy en Excel (ver Libro1.pdf):
 *
 *   - Identificación contable (sap_code)
 *   - Asignación operativa (project_id, field, location, management)
 *   - Línea/IMEI para celulares
 *   - Plan de mantenimiento (last_maintenance_at, interval, responsable)
 *   - Compra y garantía
 *
 * Esto reemplaza la hoja Excel y permite que el inventario quede vivo
 * dentro del helpdesk con trazabilidad por activo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // ── Identificación contable / proyecto ────────────────────
            $table->string('sap_code', 60)->nullable()->after('serial_number')
                ->comment('Código SAP / contable del activo');

            $table->foreignId('project_id')->nullable()->after('department_id')
                ->constrained('projects')->nullOnDelete();

            // ── Ubicación operativa (campo / zona) ────────────────────
            $table->string('field', 100)->nullable()->after('project_id')
                ->comment('Campo operativo (ej: PORE, SAN MARTIN, CARUPANA)');
            $table->string('location_zone', 100)->nullable()->after('field')
                ->comment('Zona dentro del campo (ej: ZONA 4)');
            $table->string('management_area', 120)->nullable()->after('location_zone')
                ->comment('Gerencia organizacional (ej: HSEQ, Operaciones)');

            // ── Línea / IMEI (solo para celulares) ────────────────────
            $table->string('phone_line', 30)->nullable()->after('mac_address');
            $table->string('imei', 30)->nullable()->after('phone_line');

            // ── Plan de mantenimiento ─────────────────────────────────
            $table->date('last_maintenance_at')->nullable()->after('last_scan_at');
            $table->unsignedSmallInteger('maintenance_interval_days')->nullable()->after('last_maintenance_at')
                ->comment('Frecuencia en días (típico 120 = trimestral)');
            $table->date('next_maintenance_at')->nullable()->after('maintenance_interval_days')
                ->comment('Calculada al guardar = last_maintenance_at + interval_days');
            $table->foreignId('maintenance_responsible_id')->nullable()->after('next_maintenance_at')
                ->constrained('users')->nullOnDelete();

            // ── Compra y garantía ─────────────────────────────────────
            $table->date('purchased_at')->nullable()->after('maintenance_responsible_id');
            $table->decimal('purchase_cost', 12, 2)->nullable()->after('purchased_at');
            $table->string('purchase_currency', 3)->nullable()->default('COP')->after('purchase_cost');
            $table->string('purchase_order', 80)->nullable()->after('purchase_currency')
                ->comment('Número de orden de compra');
            $table->string('supplier', 255)->nullable()->after('purchase_order');
            $table->date('warranty_expires_at')->nullable()->after('supplier');

            // Índices para los queries de listado y mantenimiento.
            $table->index('project_id');
            $table->index('next_maintenance_at');
            $table->index(['status', 'next_maintenance_at']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['maintenance_responsible_id']);

            $table->dropIndex(['project_id']);
            $table->dropIndex(['next_maintenance_at']);
            $table->dropIndex(['status', 'next_maintenance_at']);

            $table->dropColumn([
                'sap_code',
                'project_id',
                'field',
                'location_zone',
                'management_area',
                'phone_line',
                'imei',
                'last_maintenance_at',
                'maintenance_interval_days',
                'next_maintenance_at',
                'maintenance_responsible_id',
                'purchased_at',
                'purchase_cost',
                'purchase_currency',
                'purchase_order',
                'supplier',
                'warranty_expires_at',
            ]);
        });
    }
};
