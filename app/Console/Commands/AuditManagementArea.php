<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audita los valores actuales del campo `management_area` en las
 * tablas `assets` y `users` para preparar la migración al Select
 * fijo (Zona 1..5 + Administración).
 *
 * Muestra distribución + un score de "cercanía" al valor oficial
 * más probable, y una sugerencia inicial que el admin puede aceptar
 * o cambiar.
 *
 * NO modifica nada. Solo lee y reporta.
 */
class AuditManagementArea extends Command
{
    protected $signature = 'gerencia:audit
                            {--json : Emitir salida JSON para exportar.}';

    protected $description = 'Audita valores actuales de Gerencia en assets y users para preparar migración.';

    /**
     * Valores oficiales de la nueva lista. Cualquier otro será
     * candidato a migrarse.
     */
    protected const OFFICIAL = [
        'Zona 1', 'Zona 2', 'Zona 3', 'Zona 4', 'Zona 5', 'Administración',
    ];

    public function handle(): int
    {
        $assets = DB::table('assets')
            ->select('management_area', DB::raw('COUNT(*) as cnt'))
            ->groupBy('management_area')
            ->orderByDesc('cnt')
            ->get();

        $users = DB::table('users')
            ->select('management_area', DB::raw('COUNT(*) as cnt'))
            ->groupBy('management_area')
            ->orderByDesc('cnt')
            ->get();

        // Merge por valor: assets_count + users_count.
        $merged = [];

        foreach ($assets as $row) {
            $key = $row->management_area ?? '';
            $merged[$key] = [
                'value' => $key,
                'assets' => (int) $row->cnt,
                'users' => 0,
            ];
        }
        foreach ($users as $row) {
            $key = $row->management_area ?? '';
            if (! isset($merged[$key])) {
                $merged[$key] = [
                    'value' => $key,
                    'assets' => 0,
                    'users' => 0,
                ];
            }
            $merged[$key]['users'] = (int) $row->cnt;
        }

        $rows = collect($merged)
            ->map(function (array $r) {
                $r['total'] = $r['assets'] + $r['users'];
                $r['is_official'] = in_array($r['value'], self::OFFICIAL, true);
                $r['suggested_migration'] = $this->suggestMigration((string) $r['value']);

                return $r;
            })
            ->sortByDesc('total')
            ->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => [
                    'total_distinct_values' => $rows->count(),
                    'oficiales' => $rows->where('is_official', true)->count(),
                    'para_migrar' => $rows->where('is_official', false)->count(),
                    'total_assets' => $rows->sum('assets'),
                    'total_users' => $rows->sum('users'),
                ],
                'rows' => $rows->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('=== Auditoría del campo Gerencia (management_area) ===');
        $this->line('  Valores oficiales de la nueva lista: '.implode(', ', self::OFFICIAL));
        $this->newLine();

        $this->line('  Valores distintos encontrados: '.$rows->count());
        $this->line('  Ya coinciden con la lista oficial: '.$rows->where('is_official', true)->count());
        $this->line('  Necesitan migración: '.$rows->where('is_official', false)->count());
        $this->newLine();

        $this->table(
            ['Valor actual', 'Activos', 'Usuarios', 'Total', 'Estado', 'Sugerencia migración'],
            $rows->map(fn (array $r) => [
                $r['value'] === '' ? '(vacío)' : $r['value'],
                $r['assets'],
                $r['users'],
                $r['total'],
                $r['is_official'] ? '✓ Oficial' : '⚠ Migrar',
                $r['is_official'] ? '—' : $r['suggested_migration'],
            ])->all(),
        );

        $this->newLine();
        $this->comment('Este comando NO modifica nada. Revisá la tabla y decidí qué hacer con los valores no oficiales.');
        $this->line('Cuando decidas el mapeo, se aplica con el comando `gerencia:migrate`.');
        $this->line('Podés exportar la tabla completa con: php artisan gerencia:audit --json > gerencia-audit.json');

        return self::SUCCESS;
    }

    /**
     * Heurística simple para sugerir a qué valor oficial migrar
     * cada valor libre encontrado. NO se aplica automáticamente —
     * es solo una sugerencia visual para acelerar la decisión.
     */
    protected function suggestMigration(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        if ($normalized === '' || $normalized === 'null') {
            return 'Administración (por default)';
        }

        // Match directo por número de zona.
        foreach (['1', '2', '3', '4', '5'] as $n) {
            if (preg_match("/\\bzona\\s*{$n}\\b/i", $value)
                || preg_match("/\\bz\\s*{$n}\\b/i", $value)) {
                return "Zona {$n}";
            }
        }

        // Palabras típicas administrativas.
        $adminHints = ['administr', 'sede', 'oficina', 'hq', 'central', 'corporativ', 'gerencia general', 'tecnología', 'tecnologia', 'ti', 'sistemas', 'rrhh', 'contabilidad', 'financ', 'legal', 'compras'];
        foreach ($adminHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return 'Administración';
            }
        }

        // Palabras que probablemente son operación de campo.
        $operationHints = ['operacion', 'campo', 'produccion', 'produccion', 'hseq', 'mantenim', 'inspeccion'];
        foreach ($operationHints as $hint) {
            if (str_contains($normalized, $hint)) {
                return '?? (probablemente Zona X — necesita definición operativa)';
            }
        }

        return '?? Sin match automático — decidí manualmente';
    }
}
