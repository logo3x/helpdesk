<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proyectos / contratos de Confipetrol a los que se asignan los activos
 * de inventario. El Excel actual los maneja como pares "código + nombre"
 * (ej: 499015105 / PERENCO CARUPANA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Código numérico del proyecto/contrato');
            $table->string('name', 255)->comment('Nombre descriptivo del proyecto');
            $table->string('client', 255)->nullable()->comment('Cliente final si aplica');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
