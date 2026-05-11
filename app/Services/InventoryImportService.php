<?php

namespace App\Services;

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
 * Carga masiva del inventario desde un archivo .xlsx con la estructura
 * usada por Confipetrol (Libro1.xlsx — formato Excel IT/Operaciones).
 *
 * Auto-crea entidades relacionadas si no existen:
 *  - Proyectos (por `code`).
 *  - Usuarios (por `email` o `identification`).
 *  - Departamentos (por `name`/`slug`).
 *
 * Reporta filas inválidas sin abortar el import completo. Soporta
 * modo `--dry-run` para previsualizar cambios.
 */
class InventoryImportService
{
    /**
     * Mapeo "encabezado Excel" -> "clave normalizada".
     * Las claves del Excel se normalizan a slug minúsculas antes
     * de buscar en este mapa, así toleramos pequeñas variaciones.
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
        'custodio' => 'custodian_name',
        'identificacion' => 'identification',
        'cedula' => 'identification',
        'cargo' => 'position',
        'correo' => 'email',
        'email' => 'email',
        'proyecto' => 'project_code',
        'codigo_proyecto' => 'project_code',
        'nom_proyecto' => 'project_name',
        'nombre_proyecto' => 'project_name',
        'campo' => 'field',
        'ubicacion' => 'location_zone',
        'observacion' => 'notes',
        'observaciones' => 'notes',
        'acta' => 'acta_number',
        'linea' => 'phone_line',
        'imei' => 'imei',
        'gerencia' => 'management_area',
        'departamento' => 'department_name',
        'ultimo_mtto' => 'last_maintenance_at',
        'ultimo_mantenimiento' => 'last_maintenance_at',
        'prox_mtto' => 'next_maintenance_at',
        'proximo_mantenimiento' => 'next_maintenance_at',
        'mtto_dias' => 'maintenance_interval_days',
        'dias_mtto' => 'maintenance_interval_days',
        'estado_mantenimiento' => null,
        'responsable' => 'maintenance_responsible_name',
    ];

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
    public function importFromFile(string $absolutePath, bool $dryRun = false): array
    {
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
            $rowNumber = $index + 2; // +1 por header, +1 porque Excel arranca en 1.

            try {
                $normalized = $this->normalizeRow($row);

                if ($this->isEmpty($normalized)) {
                    $report['skipped']++;

                    continue;
                }

                $result = $this->importRow($normalized, $report);

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
     * Convierte una fila con headings heterogéneos a claves canónicas.
     *
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
        $tag = $row['tag'] ?? null;
        $serial = $row['serial'] ?? null;

        return blank($tag) && blank($serial);
    }

    /**
     * Importa una fila ya normalizada. Devuelve 'created' o 'updated'.
     *
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function importRow(array $row, array &$report): string
    {
        $project = $this->resolveProject($row, $report);
        $department = $this->resolveDepartment($row, $report);
        $custodian = $this->resolveUser($row, $department, $report);
        $maintenanceResponsible = $this->resolveMaintenanceResponsible($row, $report);

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
            'location_zone' => $row['location_zone'] ?? null,
            'management_area' => $row['management_area'] ?? null,
            'phone_line' => $row['phone_line'] ?? null,
            'imei' => isset($row['imei']) ? (string) $row['imei'] : null,
            'notes' => $row['notes'] ?? null,
            'project_id' => $project?->id,
            'user_id' => $custodian?->id,
            'department_id' => $department?->id ?? $custodian?->department_id,
            'maintenance_responsible_id' => $maintenanceResponsible?->id,
            'last_maintenance_at' => $this->parseDate($row['last_maintenance_at'] ?? null),
            'next_maintenance_at' => $this->parseDate($row['next_maintenance_at'] ?? null),
            'maintenance_interval_days' => isset($row['maintenance_interval_days'])
                ? (int) $row['maintenance_interval_days']
                : null,
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
     * Encuentra o crea el usuario custodio del activo. Prioriza email
     * (único en BD) y cae a identification si no hay email.
     *
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function resolveUser(array $row, ?Department $department, array &$report): ?User
    {
        $name = $row['custodian_name'] ?? null;
        $email = $row['email'] ?? null;
        $identification = isset($row['identification']) ? (string) $row['identification'] : null;

        if (blank($name) && blank($email) && blank($identification)) {
            return null;
        }

        $user = null;

        if ($email) {
            $user = User::query()->where('email', $email)->first();
        }

        if (! $user && $identification) {
            $user = User::query()->where('identification', $identification)->first();
        }

        if ($user) {
            $user->fill(array_filter([
                'name' => $name,
                'identification' => $identification,
                'position' => $row['position'] ?? null,
                'department_id' => $department?->id ?? $user->department_id,
            ], fn ($v) => $v !== null && $v !== ''));

            if ($user->isDirty()) {
                $user->save();
            }

            return $user;
        }

        $user = User::create([
            'name' => $name ?: ($identification ?: 'Sin nombre'),
            'email' => $email ?: $this->fabricateEmail($identification, $name),
            'password' => Hash::make(Str::random(32)),
            'identification' => $identification,
            'position' => $row['position'] ?? null,
            'department_id' => $department?->id,
        ]);

        $user->assignRole('usuario_final');

        $report['entities_created']['users']++;

        return $user;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{entities_created: array{projects: int, users: int, departments: int}}  $report
     */
    protected function resolveMaintenanceResponsible(array $row, array &$report): ?User
    {
        $name = $row['maintenance_responsible_name'] ?? null;

        if (blank($name)) {
            return null;
        }

        $user = User::query()->where('name', $name)->first();

        if ($user) {
            return $user;
        }

        // Si el responsable de mantenimiento no existe, lo creamos como
        // usuario final con email fabricado — IT puede después ajustar
        // su rol y datos en el panel admin.
        $user = User::create([
            'name' => $name,
            'email' => $this->fabricateEmail(null, $name),
            'password' => Hash::make(Str::random(32)),
        ]);

        $user->assignRole('tecnico_campo');

        $report['entities_created']['users']++;

        return $user;
    }

    protected function fabricateEmail(?string $identification, ?string $name): string
    {
        $base = $identification
            ?: ($name ? Str::slug($name) : Str::random(8));

        return Str::lower($base).'@imported.local';
    }

    protected function normalizeType(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Str::lower(trim((string) $value));
    }

    protected function normalizeStatus(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        // Mapeo permisivo: Activo/Inactivo/Bueno/Regular/Mal estado
        // -> active/inactive/active/fair/retired (los estados válidos
        // del schema actual).
        return match (Str::lower(trim((string) $value))) {
            'activo', 'active', 'bueno', 'good', 'asignado' => 'active',
            'inactivo', 'inactive' => 'inactive',
            'regular', 'fair' => 'fair',
            'mal_estado', 'malo', 'baja', 'retired', 'dado_de_baja' => 'retired',
            default => 'active',
        };
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        // PhpSpreadsheet ya nos devuelve seriales numéricos o strings,
        // dependiendo del formato. Manejamos ambos.
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
