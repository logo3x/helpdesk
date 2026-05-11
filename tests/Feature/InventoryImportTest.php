<?php

use App\Models\Asset;
use App\Models\Project;
use App\Models\User;
use App\Services\InventoryImportService;
use Database\Seeders\RoleSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/**
 * Helper que escribe un .xlsx temporal con encabezados Confipetrol y
 * las filas indicadas. Devuelve la ruta absoluta del archivo.
 *
 * @param  array<int, array<int, mixed>>  $rows
 */
function makeInventoryXlsx(array $rows): string
{
    $headers = [
        'TAG', 'Serial', 'Fabricante', 'Modelo', 'Codigo SAP', 'Tipo Activo', 'Estado',
        'Custodio', 'Identificacion', 'Cargo', 'Correo',
        'Proyecto', 'Nom_Proyecto', 'Campo', 'Ubicacion', 'Observacion',
        'Linea', 'IMEI', 'Gerencia',
        'Ultimo Mtto', 'Mtto Dias', 'Responsable',
    ];

    $sp = new Spreadsheet;
    $sheet = $sp->getActiveSheet();
    $sheet->fromArray($headers, null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = storage_path('app/test-'.uniqid('inv_', true).'.xlsx');
    (new Xlsx($sp))->save($path);

    return $path;
}

it('imports new assets and creates the related project, custodian and maintenance responsible', function () {
    $path = makeInventoryXlsx([
        [
            'TAG-T-001', 'SN-T-001', 'HP', 'Elitebook 840', 'SAP-T-1', 'laptop', 'activo',
            'Juan Test', '99999111', 'Tecnico SR', 'juan.test@imported.local',
            'TEST-PROJ-1', 'Proyecto Test 1', 'CampoX', 'Bloque A', 'Sin novedad',
            '3001234567', '350000000000001', 'Tecnologia',
            '2026-01-15', 180, 'Pedro Tecnico',
        ],
    ]);

    $service = app(InventoryImportService::class);

    $report = $service->importFromFile($path);

    expect($report['total'])->toBe(1);
    expect($report['created'])->toBe(1);
    expect($report['updated'])->toBe(0);
    expect($report['errors'])->toBe([]);
    expect($report['entities_created']['projects'])->toBe(1);
    expect($report['entities_created']['users'])->toBe(2); // custodian + maintenance responsible

    $asset = Asset::query()->where('asset_tag', 'TAG-T-001')->first();
    expect($asset)->not->toBeNull();
    expect($asset->manufacturer)->toBe('HP');
    expect($asset->type)->toBe('laptop');
    expect($asset->status)->toBe('active');
    expect($asset->phone_line)->toBe('3001234567');
    expect($asset->project?->code)->toBe('TEST-PROJ-1');
    expect($asset->user?->name)->toBe('Juan Test');
    expect($asset->user?->identification)->toBe('99999111');
    // El hook booted() auto-calcula next_maintenance_at = last + 180.
    expect($asset->next_maintenance_at?->toDateString())->toBe('2026-07-14');

    @unlink($path);
});

it('updates existing assets on re-import (idempotent) and does not duplicate users/projects', function () {
    $rows = [
        [
            'TAG-T-002', 'SN-T-002', 'HP', 'Elitebook 850', '', 'laptop', 'activo',
            'Maria Test', '99999222', 'Analista', 'maria.test@imported.local',
            'TEST-PROJ-2', 'Proyecto Test 2', '', '', '',
            '', '', '',
            '', '', '',
        ],
    ];
    $path = makeInventoryXlsx($rows);

    $service = app(InventoryImportService::class);

    $service->importFromFile($path);
    $report = $service->importFromFile($path);

    expect($report['created'])->toBe(0);
    expect($report['updated'])->toBe(1);
    expect($report['entities_created']['projects'])->toBe(0);
    expect($report['entities_created']['users'])->toBe(0);

    expect(Asset::query()->where('asset_tag', 'TAG-T-002')->count())->toBe(1);
    expect(Project::query()->where('code', 'TEST-PROJ-2')->count())->toBe(1);
    expect(User::query()->where('email', 'maria.test@imported.local')->count())->toBe(1);

    @unlink($path);
});

it('rolls back everything when run in dry-run mode', function () {
    $path = makeInventoryXlsx([
        [
            'TAG-T-003', 'SN-T-003', '', '', '', '', '',
            'Carlos Test', '99999333', '', 'carlos.test@imported.local',
            'TEST-PROJ-3', 'Proyecto Test 3', '', '', '',
            '', '', '',
            '', '', '',
        ],
    ]);

    $service = app(InventoryImportService::class);

    $report = $service->importFromFile($path, dryRun: true);

    expect($report['created'])->toBe(1);
    expect(Asset::query()->where('asset_tag', 'TAG-T-003')->exists())->toBeFalse();
    expect(Project::query()->where('code', 'TEST-PROJ-3')->exists())->toBeFalse();
    expect(User::query()->where('email', 'carlos.test@imported.local')->exists())->toBeFalse();

    @unlink($path);
});

it('skips rows without TAG nor Serial (does not abort the batch)', function () {
    $path = makeInventoryXlsx([
        // Fila válida.
        [
            'TAG-T-004', 'SN-T-004', 'Dell', 'Latitude', '', 'desktop', 'activo',
            'Ana Test', '99999444', '', 'ana.test@imported.local',
            '', '', '', '', '',
            '', '', '',
            '', '', '',
        ],
        // Fila con datos pero sin TAG ni Serial — el importador la salta.
        [
            '', '', 'Manufacturer huérfano', 'Modelo X', '', '', '',
            '', '', '', '',
            '', '', '', '', 'Observación sin equipo',
            '', '', '',
            '', '', '',
        ],
    ]);

    $service = app(InventoryImportService::class);
    $report = $service->importFromFile($path);

    expect($report['total'])->toBe(2);
    expect($report['created'])->toBe(1);
    expect($report['skipped'])->toBe(1);
    expect($report['errors'])->toBe([]);

    @unlink($path);
});
