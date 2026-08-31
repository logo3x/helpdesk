<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceFrequency;
use App\Models\ScheduledMaintenance;
use Illuminate\Console\Command;

/**
 * Auditoría de ciclos de mantenimiento (Fase 4 Sprint 7, 2026-08-25).
 *
 * Detecta pares padre-hijo (`parent_id`) donde la distancia entre
 * `scheduled_at` NO coincide con los días declarados por la
 * frecuencia. Sirve para encontrar los casos reportados de
 * "cuatrimestral generó ciclo a 90 días" en vez de 120.
 *
 * Si aparecen, muestra el detalle para que el admin pueda decidir:
 *   - Corregir manualmente la fecha del hijo.
 *   - O ignorar si era intencional (ej: mtto adelantado por prioridad).
 *
 * NO modifica nada.
 */
class AuditMaintenanceCycles extends Command
{
    protected $signature = 'mantenimientos:audit-ciclos {--fix : Corregir fechas de hijos incorrectas.}';

    protected $description = 'Detecta pares padre-hijo con distancia de días incorrecta según su frecuencia.';

    public function handle(): int
    {
        $expectedDays = [
            MaintenanceFrequency::Cuatrimestral->value => 120,
            MaintenanceFrequency::Anual->value => 365,
        ];

        // Cargamos padres con hijos generados por el observer.
        $childrenWithParent = ScheduledMaintenance::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->get();

        $mismatches = [];

        foreach ($childrenWithParent as $child) {
            $parent = $child->parent;
            if (! $parent) {
                continue;
            }

            $freq = $parent->frequency?->value ?? $child->frequency?->value;
            if (! $freq || ! isset($expectedDays[$freq])) {
                continue;
            }

            $expected = $expectedDays[$freq];
            $actual = (int) $parent->scheduled_at->diffInDays($child->scheduled_at, absolute: true);

            if ($actual !== $expected) {
                $mismatches[] = [
                    'parent_id' => $parent->id,
                    'child_id' => $child->id,
                    'frequency' => $freq,
                    'expected_days' => $expected,
                    'actual_days' => $actual,
                    'parent_date' => $parent->scheduled_at->toDateString(),
                    'child_date' => $child->scheduled_at->toDateString(),
                    'corrected_child_date' => $parent->scheduled_at->copy()->addDays($expected)->toDateString(),
                    'child_status' => $child->status?->value,
                ];
            }
        }

        $this->newLine();
        $this->info('=== Auditoría de ciclos de mantenimiento ===');
        $this->line('  Total pares padre-hijo analizados: '.$childrenWithParent->count());
        $this->line('  Con inconsistencia: '.count($mismatches));
        $this->newLine();

        if ($mismatches === []) {
            $this->info('✓ Todos los ciclos están correctos.');

            return self::SUCCESS;
        }

        $this->warn('⚠ Se detectaron pares con distancia incorrecta:');
        $this->table(
            ['Padre', 'Hijo', 'Frec.', 'Esperado', 'Real', 'Fecha padre', 'Fecha hijo actual', 'Fecha hijo corregida', 'Estado hijo'],
            collect($mismatches)->map(fn ($m) => [
                '#'.$m['parent_id'],
                '#'.$m['child_id'],
                $m['frequency'],
                $m['expected_days'].' d',
                $m['actual_days'].' d',
                $m['parent_date'],
                $m['child_date'],
                $m['corrected_child_date'],
                $m['child_status'],
            ])->all(),
        );

        if (! $this->option('fix')) {
            $this->newLine();
            $this->comment('Para corregir automáticamente las fechas de los hijos:');
            $this->line('  php artisan mantenimientos:audit-ciclos --fix');
            $this->comment('Solo se corregirán hijos en estado Pendiente. Los ya cerrados quedan intactos.');

            return self::SUCCESS;
        }

        // Fix — solo hijos Pendiente.
        if (! $this->confirm('¿Corregir las fechas de los hijos Pendiente listados arriba?', false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($mismatches as $m) {
            if ($m['child_status'] !== 'pendiente') {
                continue;
            }

            $child = ScheduledMaintenance::find($m['child_id']);
            if (! $child) {
                continue;
            }

            $child->forceFill(['scheduled_at' => $m['corrected_child_date']])->saveQuietly();
            $fixed++;
        }

        $this->info("✓ {$fixed} hijos corregidos.");

        return self::SUCCESS;
    }
}
