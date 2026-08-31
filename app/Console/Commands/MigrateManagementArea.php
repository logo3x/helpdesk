<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Migra los valores heredados del campo `management_area` en `assets`
 * y `users` a las 6 opciones oficiales:
 *   Zona 1, Zona 2, Zona 3, Zona 4, Zona 5, Administración.
 *
 * Mapeo decidido con Luis el 2026-08-25 tras `gerencia:audit`:
 *   - Variantes case/tildes de Zona 1..5 y Administración → normalizar.
 *   - TI → Administración.
 *   - Todo lo demás (áreas operativas, clientes, geográficas, nombres
 *     propios, vacíos) → Administración.
 *
 * Antes de aplicar corre en dry-run y pide confirmación. Genera un
 * respaldo JSON de los valores previos por si hay que revertir.
 */
class MigrateManagementArea extends Command
{
    protected $signature = 'gerencia:migrate
                            {--dry-run : Simula sin escribir en BD.}
                            {--yes : Salta la confirmación interactiva (para automatización).}';

    protected $description = 'Migra los valores libres del campo Gerencia a las 6 opciones oficiales.';

    protected const OFFICIAL = [
        'Zona 1', 'Zona 2', 'Zona 3', 'Zona 4', 'Zona 5', 'Administración',
    ];

    /**
     * Mapeo explícito. Cualquier valor no listado aquí y no oficial
     * cae a 'Administración' por default.
     *
     * @var array<string, string>
     */
    protected const EXPLICIT_MAP = [
        // Variantes de Zona 1..5
        'zona 1' => 'Zona 1',
        'ZONA 4' => 'Zona 4',
        'zona 4' => 'Zona 4',
        'Zona4' => 'Zona 4',

        // Variantes de Administración
        'Administracion' => 'Administración',
        'ADMINISTRATIVA' => 'Administración',
        'TI' => 'Administración',
    ];

    /** Valor default para todo lo demás no reconocido. */
    protected const DEFAULT_TARGET = 'Administración';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipConfirm = (bool) $this->option('yes');

        $this->newLine();
        $this->info('=== Migración del campo Gerencia (management_area) ===');
        $this->line('  Valores oficiales: '.implode(', ', self::OFFICIAL));
        $this->line('  Mapeo explícito: '.count(self::EXPLICIT_MAP).' entradas.');
        $this->line('  Default (para valores no listados y no oficiales): '.self::DEFAULT_TARGET);
        $this->newLine();

        // Recolectar valores distintos actuales.
        $distinctAssets = DB::table('assets')
            ->select('management_area', DB::raw('COUNT(*) as cnt'))
            ->groupBy('management_area')
            ->get();

        $distinctUsers = DB::table('users')
            ->select('management_area', DB::raw('COUNT(*) as cnt'))
            ->groupBy('management_area')
            ->get();

        // Merge por valor.
        $rows = [];
        foreach ($distinctAssets as $r) {
            $key = $r->management_area ?? '';
            $rows[$key] = [
                'value' => $key,
                'assets' => (int) $r->cnt,
                'users' => 0,
            ];
        }
        foreach ($distinctUsers as $r) {
            $key = $r->management_area ?? '';
            if (! isset($rows[$key])) {
                $rows[$key] = ['value' => $key, 'assets' => 0, 'users' => 0];
            }
            $rows[$key]['users'] = (int) $r->cnt;
        }

        // Calcular destino para cada uno + agrupar impacto.
        $plan = [];
        $totalAffected = 0;

        foreach ($rows as $row) {
            $current = $row['value'];
            $target = $this->resolveTarget($current);

            if ($current === $target) {
                continue; // ya está en el valor oficial correcto.
            }

            $plan[] = [
                'from' => $current === '' ? '(vacío)' : $current,
                'from_raw' => $current,
                'to' => $target,
                'assets' => $row['assets'],
                'users' => $row['users'],
                'total' => $row['assets'] + $row['users'],
            ];
            $totalAffected += $row['assets'] + $row['users'];
        }

        if ($plan === []) {
            $this->info('✓ Nada que migrar. Todos los valores ya coinciden con la lista oficial.');

            return self::SUCCESS;
        }

        // Mostrar plan.
        $this->comment('=== Plan de migración ===');
        $this->table(
            ['Valor actual', '→', 'Nuevo valor', 'Activos', 'Usuarios', 'Total'],
            collect($plan)
                ->sortByDesc('total')
                ->map(fn ($p) => [
                    $p['from'],
                    '→',
                    $p['to'],
                    $p['assets'],
                    $p['users'],
                    $p['total'],
                ])
                ->all(),
        );

        // Resumen por destino.
        $this->newLine();
        $this->comment('=== Resumen por destino ===');
        $summary = collect($plan)
            ->groupBy('to')
            ->map(fn ($items, $to) => [
                'destino' => $to,
                'valores_distintos' => count($items),
                'total_registros' => collect($items)->sum('total'),
            ])
            ->values();

        $this->table(
            ['Destino', 'Valores distintos', 'Total registros'],
            $summary->map(fn ($s) => [$s['destino'], $s['valores_distintos'], $s['total_registros']])->all(),
        );

        $this->newLine();
        $this->line("Total de registros que se actualizarán: <fg=yellow>{$totalAffected}</>");

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN — no se modificó nada. Corré sin --dry-run para aplicar.');

            return self::SUCCESS;
        }

        if (! $skipConfirm && ! $this->confirm("¿Aplicar la migración de {$totalAffected} registros ahora?", false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        // Backup previo — snapshot de los pares (id, valor original)
        // en un archivo JSON dentro de storage/app.
        $backupPath = 'gerencia-backup-'.now()->format('Ymd-His').'.json';
        // Snapshot completo (todas las filas, incluidas las que son NULL)
        // así el rollback es reversible sin necesitar joins con otras tablas.
        $backup = [
            'timestamp' => now()->toIso8601String(),
            'assets' => DB::table('assets')
                ->select('id', 'management_area')
                ->get()
                ->toArray(),
            'users' => DB::table('users')
                ->select('id', 'management_area')
                ->get()
                ->toArray(),
        ];
        Storage::disk('local')->put($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line("Backup escrito en storage/app/{$backupPath}");

        // Aplicar migración.
        DB::beginTransaction();
        $updatedAssets = 0;
        $updatedUsers = 0;

        try {
            foreach ($plan as $p) {
                // Escapado por el WHERE con valor literal (incluye vacío tratado como NULL).
                if ($p['from_raw'] === '') {
                    $updatedAssets += DB::table('assets')
                        ->whereNull('management_area')
                        ->update(['management_area' => $p['to']]);
                    $updatedUsers += DB::table('users')
                        ->whereNull('management_area')
                        ->update(['management_area' => $p['to']]);
                } else {
                    $updatedAssets += DB::table('assets')
                        ->where('management_area', $p['from_raw'])
                        ->update(['management_area' => $p['to']]);
                    $updatedUsers += DB::table('users')
                        ->where('management_area', $p['from_raw'])
                        ->update(['management_area' => $p['to']]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Falló la migración — se hizo rollback. Detalle: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✓ Migración completada: {$updatedAssets} activos + {$updatedUsers} usuarios actualizados.");
        $this->line("Para revertir: leer el JSON en storage/app/{$backupPath} y restaurar manualmente.");

        return self::SUCCESS;
    }

    /**
     * Resuelve el destino para un valor actual dado.
     */
    protected function resolveTarget(string $current): string
    {
        // Ya es oficial → no se toca.
        if (in_array($current, self::OFFICIAL, true)) {
            return $current;
        }

        // Match explícito en el mapa.
        if (isset(self::EXPLICIT_MAP[$current])) {
            return self::EXPLICIT_MAP[$current];
        }

        // Default: Administración.
        return self::DEFAULT_TARGET;
    }
}
