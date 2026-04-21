<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices faltantes detectados en auditoría de calidad:
     *   - users.azure_id (lookup en SSO callback)
     *   - tickets.sla_config_id (checkBreaches filtra por whereNotNull)
     *   - tickets.department_id (scope por depto se usa en TODOS los
     *     queries de listing de soporte)
     *   - kb_articles.department_id (filtro por depto en RAG + listings)
     */
    public function up(): void
    {
        $this->safeIndex('users', 'azure_id');
        $this->safeIndex('tickets', 'sla_config_id');
        $this->safeIndex('tickets', 'department_id');
        $this->safeIndex('kb_articles', 'department_id');
    }

    public function down(): void
    {
        $this->safeDropIndex('users', 'azure_id');
        $this->safeDropIndex('tickets', 'sla_config_id');
        $this->safeDropIndex('tickets', 'department_id');
        $this->safeDropIndex('kb_articles', 'department_id');
    }

    /**
     * Crea el índice en la columna si la tabla tiene la columna.
     * Usa try/catch para compatibilidad con SQLite e idempotencia entre
     * `php artisan migrate` repetidas (SQLite no tiene information_schema).
     */
    protected function safeIndex(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($column));
        } catch (Throwable $e) {
            // Índice ya existe — silenciar para permitir re-runs de tests.
        }
    }

    protected function safeDropIndex(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex("{$table}_{$column}_index"));
        } catch (Throwable $e) {
            // no-op
        }
    }
};
