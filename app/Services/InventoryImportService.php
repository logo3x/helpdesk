<?php

namespace App\Services;

use App\Enums\ManagementArea;
use App\Models\Asset;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Carbon as SupportCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Carga masiva del inventario desde .xlsx (Sprint 5 — rediseñado
 * 2026-08-25).
 *
 * Cambios respecto a la versión anterior:
 *  - Búsqueda de custodio: CÉDULA primero, email después. Antes era al
 *    revés, lo que causaba duplicados cuando el email no era conocido.
 *  - NO fabrica emails `@imported.local`. Si no hay email, deja
 *    `{cedula}@sin-email.local` (compatible con Sprint 2).
 *  - Regla del email idéntica a `PeopleImportService`:
 *      · @confipetrol.com → is_azure_pending=true, password random.
 *      · otro / vacío → cuenta local, password = primeros 8 de cédula,
 *        password_must_change=true.
 *  - Enum de tipos normalizado (12 valores válidos).
 *  - En UPDATE nunca cambia password ni rol de un usuario existente
 *    (respeta el panel — mismo criterio que Sprint 1 y Sprint 2).
 *  - Modo estricto opcional: rechaza filas cuyo custodio no exista.
 */
class InventoryImportService
{
    /**
     * Mapeo header slug → clave canónica. Alineado con la plantilla
     * v2 del `InventoryTemplateService`.
     */
    protected const HEADER_MAP = [
        'tag' => 'tag',
        'serial' => 'serial',
        'fabricante' => 'manufacturer',
        'modelo' => 'model',
        'codigo_sap' => 'sap_code',
        'sap' => 'sap_code',
        'tipo_activo' => 'type',
        'tipo' => 'type',
        'estado' => 'status',
        'identificacion' => 'identification',
        'cedula' => 'identification',
        'custodio' => 'custodian_name',
        'cargo' => 'position',
        'correo' => 'email',
        'email' => 'email',
        'departamento' => 'department_name',
        'proyecto' => 'project_code',
        'codigo_proyecto' => 'project_code',
        'nom_proyecto' => 'project_name',
        'nombre_proyecto' => 'project_name',
        'campo' => 'field',
        'ubicacion' => 'location_zone',
        'zona' => 'location_zone_extended',
        'gerencia' => 'management_area',
        'linea' => 'phone_line',
        'imei' => 'imei',
        'observacion' => 'notes',
        'observaciones' => 'notes',
    ];

    protected const VALID_TYPES = InventoryTemplateService::VALID_TYPES;

    protected const VALID_STATUSES = InventoryTemplateService::VALID_STATUSES;

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
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: array<int, array{row: int, tag: ?string, message: string}>,
     *     entities_created: array{projects: int, users: int, departments: int}
     * }
     */
    public function importFromFile(
        string $absolutePath,
        bool $dryRun = false,
        bool $strictCustodian = false,
    ): array {
        $rows = $this->readRows($absolutePath);

        $report = [
            'total' => count($rows),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'entities_created' => [
                'projects' => 0,
                'users' => 0,
                'departments' => 0,
            ],
        ];

        DB::beginTransaction();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $normalized = $this->normalizeRow($row);

                if ($this->isEmpty($normalized)) {
                    $report['skipped']++;

                    continue;
                }

                $result = $this->importRow($normalized, $report, $strictCustodian);

                if ($result === 'skipped') {
                    $report['skipped']++;

                    continue;
                }

                $report[$result === 'created' ? 'created' : 'updated']++;
            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'row' => $rowNumber,
                    'tag' => $row['tag'] ?? null,
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
     */
    protected function isEmpty(array $row): bool
    {
        return blank($row['tag'] ?? null) && blank($row['serial'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     * @return 'created'|'updated'|'skipped'
     */
    protected function importRow(array $row, array &$report, bool $strictCustodian): string
    {
        $project = $this->resolveProject($row, $report);
        $department = $this->resolveDepartment($row, $report);
        $custodian = $this->resolveCustodian($row, $department, $report, $strictCustodian);

        // Modo estricto: si el custodio no existe y NO se creó stub,
        // saltamos la fila para que IT complete la lista de personas.
        if ($strictCustodian && $custodian === null && ! blank($row['identification'] ?? null)) {
            throw new \InvalidArgumentException(
                "Modo estricto: el custodio con cédula {$row['identification']} no existe en el sistema. ".
                'Precargalo desde /admin/users → Precargar personas antes de subir el inventario.'
            );
        }

        $tag = $row['tag'] ?? null;
        $serial = $row['serial'] ?? null;

        if (blank($tag) && blank($serial)) {
            throw new \InvalidArgumentException('La fila no tiene TAG ni Serial — no se puede identificar el activo.');
        }

        $asset = Asset::query()
            ->when($tag, fn ($q) => $q->where('asset_tag', $tag))
            ->when(! $tag && $serial, fn ($q) => $q->where('serial_number', $serial))
            ->first();

        $isNew = $asset === null;
        $asset ??= new Asset;

        $asset->fill(array_filter([
            'asset_tag' => $tag,
            'serial_number' => $serial,
            'manufacturer' => $row['manufacturer'] ?? null,
            'model' => $row['model'] ?? null,
            'sap_code' => $row['sap_code'] ?? null,
            'type' => $this->normalizeType($row['type'] ?? null),
            'status' => $this->normalizeStatus($row['status'] ?? null),
            'field' => $row['field'] ?? null,
            'location_zone' => $row['location_zone_extended'] ?? $row['location_zone'] ?? null,
            'management_area' => ManagementArea::tryNormalize($row['management_area'] ?? null)?->value,
            'phone_line' => $row['phone_line'] ?? null,
            'imei' => isset($row['imei']) ? (string) $row['imei'] : null,
            'notes' => $row['notes'] ?? null,
            'project_id' => $project?->id,
            'user_id' => $custodian?->id,
            'department_id' => $department?->id ?? $custodian?->department_id,
        ], fn ($v) => $v !== null && $v !== ''));

        $asset->save();

        return $isNew ? 'created' : 'updated';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function resolveProject(array $row, array &$report): ?Project
    {
        $code = $row['project_code'] ?? null;
        if (blank($code)) {
            return null;
        }

        $code = (string) $code;
        $project = Project::query()->where('code', $code)->first();
        if ($project) {
            return $project;
        }

        $project = Project::create([
            'code' => $code,
            'name' => $row['project_name'] ?? $code,
            'is_active' => true,
        ]);

        $report['entities_created']['projects']++;

        return $project;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
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

        $report['entities_created']['departments']++;

        return $department;
    }

    /**
     * Resuelve el custodio del activo. Prioridad:
     *   1. Cédula (identification) — clave única y estándar de RRHH.
     *   2. Email — fallback.
     * Si no existe con ninguna, crea un stub (a menos que $strict).
     *
     * En update NUNCA cambia password ni rol.
     *
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function resolveCustodian(
        array $row,
        ?Department $department,
        array &$report,
        bool $strict,
    ): ?User {
        $name = $row['custodian_name'] ?? null;
        $email = ! empty($row['email']) ? mb_strtolower((string) $row['email']) : null;
        $identification = isset($row['identification']) ? (string) $row['identification'] : null;

        if (blank($name) && blank($email) && blank($identification)) {
            return null;
        }

        // Búsqueda por cédula primero.
        $user = null;
        if ($identification) {
            $user = User::query()->where('identification', $identification)->first();
        }

        // Fallback: por email.
        if (! $user && $email) {
            $user = User::query()->where('email', $email)->first();
        }

        if ($user) {
            $user->fill(array_filter([
                'name' => $user->name ?: $name,
                'identification' => $user->identification ?: $identification,
                'position' => $row['position'] ?? null,
                'department_id' => $department?->id ?? $user->department_id,
            ], fn ($v) => $v !== null && $v !== ''));

            if ($user->isDirty()) {
                $user->save();
            }

            return $user;
        }

        // No existe. En modo estricto devolvemos null para que el
        // caller reporte el error.
        if ($strict) {
            return null;
        }

        // Crear stub con la regla del email del Sprint 2.
        return $this->createCustodianStub($row, $department, $report);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function createCustodianStub(array $row, ?Department $department, array &$report): User
    {
        $name = $row['custodian_name'] ?? null;
        $email = ! empty($row['email']) ? mb_strtolower((string) $row['email']) : null;
        $identification = isset($row['identification']) ? (string) $row['identification'] : null;

        $isAzure = $email !== null && $this->isAzureEmail($email);

        // Email: si no viene y hay cédula, sintético. Si no hay cédula
        // ni email, generamos un placeholder — no ideal pero mejor que
        // fallar el importer.
        $resolvedEmail = $email
            ?? ($identification ? $identification.'@sin-email.local' : Str::random(12).'@sin-email.local');

        $attrs = [
            'name' => $name ?: ($identification ?: 'Sin nombre'),
            'email' => $resolvedEmail,
            'identification' => $identification,
            'position' => $row['position'] ?? null,
            'department_id' => $department?->id,
        ];

        if ($isAzure) {
            $attrs['password'] = Hash::make(Str::random(60));
            $attrs['is_azure_pending'] = true;
            $attrs['password_must_change'] = false;
        } else {
            $onlyDigits = $identification ? (preg_replace('/\D/', '', $identification) ?: $identification) : Str::random(8);
            $initialPassword = str_pad(mb_substr($onlyDigits, 0, 8), 8, '0', STR_PAD_LEFT);
            $attrs['password'] = Hash::make($initialPassword);
            $attrs['is_azure_pending'] = false;
            $attrs['password_must_change'] = true;
            $attrs['email_verified_at'] = now();
        }

        $user = User::create($attrs);
        $user->assignRole('usuario_final');
        $report['entities_created']['users']++;

        return $user;
    }

    protected function isAzureEmail(string $email): bool
    {
        $domain = mb_strtolower(Str::after($email, '@'));

        return in_array($domain, $this->azureDomains(), true);
    }

    protected function normalizeType(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $v = Str::lower(trim((string) $value));

        // Aliases comunes en español que las cargas históricas usaban.
        $aliases = [
            'pc' => 'desktop',
            'computador' => 'desktop',
            'escritorio' => 'desktop',
            'portatil' => 'laptop',
            'todo_en_uno' => 'all_in_one',
            'todo en uno' => 'all_in_one',
            'aio' => 'all_in_one',
            'impresora' => 'printer',
            'servidor' => 'server',
            'celular' => 'phone',
            'telefono' => 'phone',
            'radio' => 'radio',
            'antena' => 'antenna',
            'kit_de_red' => 'network_kit',
            'kit de red' => 'network_kit',
            'ups' => 'ups',
            'pantalla' => 'monitor',
            'monitor' => 'monitor',
        ];

        $normalized = $aliases[$v] ?? $v;

        return in_array($normalized, self::VALID_TYPES, true) ? $normalized : 'other';
    }

    protected function normalizeStatus(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return match (Str::lower(trim((string) $value))) {
            'activo', 'active', 'bueno', 'good', 'asignado' => 'active',
            'regular', 'fair' => 'fair',
            'reparacion', 'reparación', 'in_repair', 'en_reparacion' => 'in_repair',
            'mal_estado', 'malo', 'baja', 'retired', 'dado_de_baja', 'inactive', 'inactivo' => 'retired',
            default => 'active',
        };
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return SupportCarbon::instance(
                Date::excelToDateTimeObject((float) $value),
            );
        }

        try {
            return SupportCarbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
