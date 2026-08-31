<?php

namespace App\Services;

use App\Enums\ManagementArea;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Precarga masiva de personas desde .xlsx.
 *
 * Reglas:
 *  - identification (cédula) es OBLIGATORIA y es la clave única.
 *  - Si ya existe un usuario con esa cédula → se actualiza.
 *  - Si el email termina en @confipetrol.com (dominio Azure autorizado):
 *      · password = random 60 chars (inutilizable)
 *      · is_azure_pending = true
 *      · password_must_change = false
 *  - Si el email es otro dominio o vacío:
 *      · password = primeros 8 dígitos de la cédula (si menor, se
 *        rellena con "0" a la izquierda hasta 8).
 *      · is_azure_pending = false
 *      · password_must_change = true
 *  - El rol se asigna con syncRoles solo cuando el usuario es NUEVO.
 *    En actualizaciones se respeta el rol existente (regla del panel
 *    fija los roles — mismo criterio que el fix del SSO 2026-08-24).
 */
class PeopleImportService
{
    /**
     * @var array<string, string> Header slug → clave canónica.
     */
    protected const HEADER_MAP = [
        'identificacion' => 'identification',
        'cedula' => 'identification',
        'nombre_completo' => 'name',
        'nombre' => 'name',
        'email' => 'email',
        'correo' => 'email',
        'cargo' => 'position',
        'departamento' => 'department_name',
        'rol' => 'role',
        'telefono' => 'phone',
        'gerencia' => 'management_area',
    ];

    protected const VALID_ROLES = [
        'usuario_final',
        'agente_soporte',
        'supervisor_soporte',
        'tecnico_campo',
        'editor_kb',
        'admin',
        'super_admin',
    ];

    /**
     * Dominios cuyos usuarios entran por Azure. Configurable en
     * config/services.azure.allowed_domains (mismo que valida el SSO).
     */
    protected function azureDomains(): array
    {
        return collect(
            explode(',', (string) config('services.azure.allowed_domains', 'confipetrol.com'))
        )
            ->map(fn ($d) => trim(mb_strtolower($d)))
            ->filter()
            ->all();
    }

    /**
     * @return array{
     *     total: int,
     *     created_azure: int,
     *     created_local: int,
     *     updated: int,
     *     skipped: int,
     *     errors: array<int, array{row: int, identification: ?string, message: string}>,
     *     departments_created: int,
     * }
     */
    public function importFromFile(string $absolutePath, bool $dryRun = false): array
    {
        $rows = $this->readRows($absolutePath);

        $report = [
            'total' => count($rows),
            'created_azure' => 0,
            'created_local' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'departments_created' => 0,
        ];

        DB::beginTransaction();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 header, +1 base 1 de Excel.

            try {
                $normalized = $this->normalizeRow($row);

                $identification = $normalized['identification'] ?? null;
                $name = $normalized['name'] ?? null;

                if (blank($identification)) {
                    $report['skipped']++;

                    continue;
                }

                if (blank($name)) {
                    throw new \InvalidArgumentException('El nombre completo es obligatorio.');
                }

                $result = $this->upsertPerson($normalized, $report);

                $report[$result]++;
            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'row' => $rowNumber,
                    'identification' => isset($row['identificacion'])
                        ? (string) $row['identificacion']
                        : (isset($row['cedula']) ? (string) $row['cedula'] : null),
                    'message' => $e->getMessage(),
                ];
            }
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return $report;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readRows(string $absolutePath): array
    {
        $importer = new class implements ToArray, WithHeadingRow
        {
            /** @var array<int, array<string, mixed>> */
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($importer, $absolutePath);

        return $importer->rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $slug = Str::slug((string) $key, '_');
            $canonical = self::HEADER_MAP[$slug] ?? null;
            if ($canonical === null) {
                continue;
            }
            $normalized[$canonical] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{departments_created: int}  $report
     * @return 'created_azure'|'created_local'|'updated'
     */
    protected function upsertPerson(array $row, array &$report): string
    {
        $identification = (string) $row['identification'];
        $email = ! empty($row['email']) ? mb_strtolower((string) $row['email']) : null;
        $name = (string) $row['name'];
        $position = $row['position'] ?? null;
        $phone = $row['phone'] ?? null;
        // Normalizamos gerencia a uno de los 6 valores oficiales.
        // Si viene algo no reconocido, se guarda NULL (queda vacío en
        // el form y el usuario/admin lo debe corregir después).
        $managementArea = ManagementArea::tryNormalize($row['management_area'] ?? null)?->value;

        $department = $this->resolveDepartment($row, $report);
        $isAzure = $email !== null && $this->isAzureEmail($email);

        $user = User::query()
            ->where('identification', $identification)
            ->first();

        $isNew = $user === null;

        if ($isNew) {
            $user = new User;
        }

        // Datos comunes.
        $user->fill(array_filter([
            'identification' => $identification,
            'name' => $name,
            'position' => $position,
            'phone' => $phone,
            'management_area' => $managementArea,
            'department_id' => $department?->id ?? $user->department_id,
        ], fn ($v) => $v !== null && $v !== ''));

        // Email: si viene en el Excel se setea. Si no viene:
        //   - usuario nuevo → email sintético {cedula}@sin-email.local
        //     (la BD requiere NOT NULL). El usuario podrá cambiarlo
        //     luego desde el perfil.
        //   - usuario existente → respetar el que ya tenía.
        if ($email !== null) {
            $user->email = $email;
        } elseif ($isNew) {
            $user->email = $identification.'@sin-email.local';
        }

        if ($isNew) {
            if ($isAzure) {
                // Cuenta Azure pendiente.
                $user->password = Hash::make(Str::random(60));
                $user->is_azure_pending = true;
                $user->password_must_change = false;
                $user->email_verified_at = null;
            } else {
                // Cuenta local — password = primeros 8 dígitos de la cédula.
                $onlyDigits = preg_replace('/\D/', '', $identification) ?: $identification;
                $initialPassword = str_pad(mb_substr($onlyDigits, 0, 8), 8, '0', STR_PAD_LEFT);

                $user->password = Hash::make($initialPassword);
                $user->is_azure_pending = false;
                $user->password_must_change = true;
                $user->email_verified_at = now();
            }
        }
        // En updates NO tocamos password ni flags de estado — respetamos
        // lo que haya. Solo actualizamos datos maestros de arriba.

        $user->save();

        // Rol: solo en creación. Nunca en update — el panel manda.
        if ($isNew) {
            $requestedRole = $this->normalizeRole($row['role'] ?? null);
            $user->syncRoles([$requestedRole]);
        }

        if ($isNew) {
            return $isAzure ? 'created_azure' : 'created_local';
        }

        return 'updated';
    }

    protected function isAzureEmail(string $email): bool
    {
        $domain = mb_strtolower(Str::after($email, '@'));

        return in_array($domain, $this->azureDomains(), true);
    }

    protected function normalizeRole(?string $value): string
    {
        $value = trim(mb_strtolower((string) $value));

        return in_array($value, self::VALID_ROLES, true) ? $value : 'usuario_final';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{departments_created: int}  $report
     */
    protected function resolveDepartment(array $row, array &$report): ?Department
    {
        $name = $row['department_name'] ?? null;
        if (blank($name)) {
            return null;
        }

        $slug = Str::slug((string) $name);
        $department = Department::query()->where('slug', $slug)->first();
        if ($department) {
            return $department;
        }

        $department = Department::create([
            'name' => (string) $name,
            'slug' => $slug,
            'is_active' => true,
        ]);

        $report['departments_created']++;

        return $department;
    }
}
