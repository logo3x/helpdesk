<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite email NULL en `users` para que el comando de limpieza
 * (Sprint 6) pueda desprender el email fabricado y forzar re-enlace
 * por cédula al primer login SSO.
 *
 * El unique constraint sobre email sigue vigente: MySQL permite
 * múltiples NULL en columnas únicas, así que no hay conflicto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // NO se revierte automáticamente — si hay emails NULL, la
            // vuelta a NOT NULL fallaría. Debe hacerse manualmente
            // limpiando primero cualquier usuario sin email.
            $table->string('email')->nullable(false)->change();
        });
    }
};
