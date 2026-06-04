<?php

namespace App\Services;

use App\DTOs\KactusEmployee;
use App\DTOs\KactusSyncResult;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Servicio de integración con Kactus (nómina).
 *
 * Sincroniza empleados desde Kactus a la tabla `users` local. La
 * conexión real con la API queda atrás del cliente HTTP de Laravel
 * (Http::fake() en tests). Mientras Hermes confirma el shape exacto,
 * `fetchEmployee*` retorna el payload tal como llega y el DTO se
 * encarga del mapeo de campos.
 */
class KactusService
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $apiKey = null,
    ) {
        $this->baseUrl = $this->baseUrl ?? config('kactus.base_url');
        $this->apiKey = $this->apiKey ?? config('kactus.api_key');
    }

    /**
     * Trae un empleado por su ID en Kactus. Retorna null si no existe
     * (404) o si la API responde con error.
     */
    public function fetchEmployee(string $kactusId): ?KactusEmployee
    {
        try {
            $response = $this->client()->get("/employees/{$kactusId}");
        } catch (\Throwable $e) {
            Log::warning('KactusService fetchEmployee exception', [
                'kactus_id' => $kactusId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('KactusService fetchEmployee failed', [
                'kactus_id' => $kactusId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return KactusEmployee::fromKactusPayload($response->json());
    }

    /**
     * Trae todos los empleados modificados desde `$since`. Si es null,
     * trae el catálogo completo. Pagina automáticamente.
     *
     * @return iterable<KactusEmployee>
     */
    public function fetchAllSince(?Carbon $since = null): iterable
    {
        $page = 1;

        do {
            $response = $this->client()->get('/employees', array_filter([
                'page' => $page,
                'modified_since' => $since?->toIso8601String(),
            ]));

            if (! $response->successful()) {
                Log::error('KactusService fetchAllSince failed', [
                    'page' => $page,
                    'status' => $response->status(),
                ]);
                break;
            }

            $data = $response->json('data', []);
            foreach ($data as $payload) {
                yield KactusEmployee::fromKactusPayload($payload);
            }

            $hasMore = (bool) $response->json('meta.has_more', false);
            $page++;
        } while ($hasMore);
    }

    /**
     * Upsert local: matchea primero por `kactus_employee_id`, luego
     * por `identification` (para "adoptar" users creados manualmente).
     * Si el empleado viene terminated, aplica la política de
     * `kactus.on_terminate`.
     */
    public function syncToUser(KactusEmployee $emp): User
    {
        $user = User::query()
            ->where('kactus_employee_id', $emp->kactusId)
            ->when($emp->identification !== '', fn ($q) => $q->orWhere('identification', $emp->identification))
            ->first();

        $isNew = ! $user;
        $user ??= new User;

        $user->forceFill([
            'name' => $emp->name,
            'identification' => $emp->identification ?: $user->identification,
            'position' => $emp->position ?? $user->position,
            'phone' => $emp->phone ?? $user->phone,
            'kactus_employee_id' => $emp->kactusId,
            'kactus_synced_at' => now(),
            'kactus_payload' => $emp->rawPayload,
            'employment_status' => $emp->status,
            'hired_at' => $emp->hiredAt?->toDateString(),
            'terminated_at' => $emp->terminatedAt?->toDateString(),
        ]);

        if ($emp->email) {
            $user->email = $emp->email;
        }

        if ($departmentId = $this->resolveDepartmentId($emp->departmentName)) {
            $user->department_id = $departmentId;
        }

        if ($isNew) {
            // Password aleatoria — el user real se loguea vía Azure SSO,
            // este password sólo previene login con guess.
            $user->password = bcrypt(Str::random(48));
            $user->email = $user->email ?? $this->fallbackEmail($emp);
            $user->email_verified_at = now();
        }

        $user->save();

        if ($isNew) {
            $user->assignRole(config('kactus.default_role'));
        }

        if ($emp->isTerminated()) {
            $this->applyTerminationPolicy($user);
        }

        return $user;
    }

    /**
     * Sync masivo desde el iterable de `fetchAllSince`. Captura errores
     * por empleado para no abortar todo el lote.
     */
    public function syncBatch(iterable $employees): KactusSyncResult
    {
        $result = new KactusSyncResult;

        foreach ($employees as $emp) {
            try {
                $userExisted = User::where('kactus_employee_id', $emp->kactusId)->exists();
                $this->syncToUser($emp);

                if ($emp->isTerminated()) {
                    $result->deactivated++;
                } elseif ($userExisted) {
                    $result->updated++;
                } else {
                    $result->created++;
                }
            } catch (\Throwable $e) {
                $result->errors[] = "Kactus#{$emp->kactusId}: {$e->getMessage()}";
                Log::error('KactusService syncBatch error', [
                    'kactus_id' => $emp->kactusId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl((string) $this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->timeout(config('kactus.timeout_seconds', 30))
            ->retry(2, 1000);
    }

    protected function resolveDepartmentId(?string $departmentName): ?int
    {
        if (blank($departmentName)) {
            return null;
        }

        $map = (array) config('kactus.department_map', []);
        if (isset($map[$departmentName])) {
            return (int) $map[$departmentName];
        }

        // Fallback: buscar por nombre exacto en la tabla local.
        return Department::query()
            ->where('name', $departmentName)
            ->value('id');
    }

    protected function applyTerminationPolicy(User $user): void
    {
        $policy = config('kactus.on_terminate', 'deactivate');

        if ($policy === 'delete') {
            $user->delete();

            return;
        }

        if ($policy === 'deactivate') {
            $user->forceFill([
                'employment_status' => 'terminated',
                'password' => bcrypt(Str::random(48)), // invalida login
            ])->save();
        }
        // 'keep' → no-op.
    }

    protected function fallbackEmail(KactusEmployee $emp): string
    {
        $localPart = Str::slug($emp->name, '.');

        return $localPart === ''
            ? "kactus.{$emp->kactusId}@confipetrol.local"
            : "{$localPart}@confipetrol.local";
    }
}
