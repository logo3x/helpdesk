<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Limpia usuarios legacy con email fabricado (@imported.local o
 *
 * @sin-email.local) que fueron creados por versiones anteriores del
 * cargue masivo del inventario.
 *
 * ¿Qué hace?
 *   - Los que tienen cédula → los marca is_azure_pending=true y borra
 *     el email fabricado (queda NULL). Cuando la persona entre por
 *     Azure, el callback los matchea por cédula (Sprint 3) y les
 *     setea el email corporativo real.
 *   - Los que NO tienen cédula → los reporta pero NO los toca. IT
 *     tiene que decidir: eliminarlos manualmente, agregarles cédula,
 *     o convertirlos en cuentas locales.
 *
 * SIEMPRE usar --dry-run primero para ver el impacto antes de aplicar.
 */
class CleanupImportedEmails extends Command
{
    protected $signature = 'users:cleanup-imported-emails
                            {--dry-run : No modifica nada, solo reporta.}
                            {--json : Salida JSON para exportar.}';

    protected $description = 'Prepara usuarios con email fabricado para enlace por Azure vía cédula.';

    /**
     * Dominios sintéticos que el sistema ha usado en distintas versiones
     * del importer. Cualquier user con email en estos dominios cae en
     * la limpieza.
     */
    protected const SYNTHETIC_DOMAINS = ['imported.local', 'sin-email.local'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $asJson = (bool) $this->option('json');

        $query = User::query();
        $query->where(function ($q) {
            foreach (self::SYNTHETIC_DOMAINS as $domain) {
                $q->orWhere('email', 'like', '%@'.$domain);
            }
        });

        $candidates = $query->get();

        // Precomputar cuántos activos tiene cada custodio con un solo
        // query. NO lo guardamos en el modelo (rompería el save
        // porque no es una columna real), lo consultamos con este map.
        $assetCounts = DB::table('assets')
            ->whereIn('user_id', $candidates->pluck('id'))
            ->select('user_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('user_id')
            ->pluck('cnt', 'user_id')
            ->toArray();

        $assetCountFor = fn (User $u): int => (int) ($assetCounts[$u->id] ?? 0);

        $ready = $candidates->filter(fn (User $u) => ! blank($u->identification))->values();
        $missing = $candidates->filter(fn (User $u) => blank($u->identification))->values();

        if ($asJson) {
            $this->line(json_encode([
                'ready' => $ready->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'identification' => $u->identification,
                    'assets_count' => $assetCountFor($u),
                ])->all(),
                'missing_identification' => $missing->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'assets_count' => $assetCountFor($u),
                ])->all(),
                'summary' => [
                    'total' => $candidates->count(),
                    'ready' => $ready->count(),
                    'missing_identification' => $missing->count(),
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Auditoría de usuarios con email fabricado');
        $this->line('  Dominios sintéticos: '.implode(', ', self::SYNTHETIC_DOMAINS));
        $this->line('  Total encontrados: '.$candidates->count());
        $this->line('  Con cédula (procesables): '.$ready->count());
        $this->line('  Sin cédula (requieren revisión manual): '.$missing->count());
        $this->newLine();

        if ($ready->isNotEmpty()) {
            $this->comment('=== Con cédula — se pueden limpiar automáticamente ===');
            $this->table(
                ['ID', 'Nombre', 'Email actual', 'Cédula', 'Activos'],
                $ready->map(fn (User $u) => [
                    $u->id,
                    Str::limit((string) $u->name, 30),
                    Str::limit((string) $u->email, 40),
                    $u->identification,
                    $assetCountFor($u),
                ])->all(),
            );
        }

        if ($missing->isNotEmpty()) {
            $this->newLine();
            $this->warn('=== Sin cédula — NO se tocan, revisá manualmente ===');
            $this->table(
                ['ID', 'Nombre', 'Email actual', 'Activos'],
                $missing->map(fn (User $u) => [
                    $u->id,
                    Str::limit((string) $u->name, 30),
                    Str::limit((string) $u->email, 40),
                    $assetCountFor($u),
                ])->all(),
            );
            $this->newLine();
            $this->line('Para estos, tenés 3 opciones:');
            $this->line('  1. Agregar cédula desde /admin/users → Editar.');
            $this->line('  2. Eliminar si son duplicados o testeo.');
            $this->line('  3. Dejar así (siguen funcionando con el email fabricado).');
        }

        if ($ready->isEmpty()) {
            $this->newLine();
            $this->info('Nada que limpiar. Todo bien.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY RUN — no se modificó nada. Corré sin --dry-run para aplicar.');

            return self::SUCCESS;
        }

        if (! $this->confirm('¿Aplicar limpieza a los '.$ready->count().' usuarios con cédula?', false)) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($ready as $user) {
            $user->forceFill([
                'email' => null,
                'is_azure_pending' => true,
            ])->saveQuietly();
            $updated++;
        }

        $this->newLine();
        $this->info("✓ {$updated} usuarios preparados para enlace por Azure.");
        $this->line('Al entrar por el botón azul, se matchean por cédula (Sprint 3) y quedan con su email corporativo real.');

        return self::SUCCESS;
    }
}
