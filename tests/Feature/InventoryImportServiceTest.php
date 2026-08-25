<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\InventoryImportService;
use App\Services\InventoryTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('usuario_final', 'web');
});

/**
 * Genera un .xlsx temporal con los headers reales de la plantilla v2.
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function generateInventoryXlsx(array $rows): string
{
    $headers = [
        'TAG', 'Serial', 'Fabricante', 'Modelo', 'Codigo SAP', 'Tipo Activo', 'Estado',
        'Identificacion', 'Custodio', 'Cargo', 'Correo', 'Departamento',
        'Proyecto', 'Nom_Proyecto', 'Campo', 'Ubicacion', 'Zona', 'Gerencia',
        'Linea', 'IMEI', 'Observacion',
    ];

    $sp = new Spreadsheet;
    $sheet = $sp->getActiveSheet();
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }
    foreach ($rows as $r => $row) {
        foreach ($headers as $i => $h) {
            $sheet->setCellValueExplicitByColumnAndRow(
                $i + 1,
                $r + 2,
                $row[$h] ?? '',
                DataType::TYPE_STRING,
            );
        }
    }

    $tmp = tempnam(sys_get_temp_dir(), 'inv_').'.xlsx';
    (new Xlsx($sp))->save($tmp);

    return $tmp;
}

it('busca custodio por cedula primero aunque el email cambie', function () {
    $existing = User::factory()->create([
        'identification' => '1121898647',
        'email' => 'luis.oviedo@confipetrol.com',
    ]);
    $existing->syncRoles(['usuario_final']);

    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-100',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Identificacion' => '1121898647',
        'Correo' => 'email.tipiado.mal@confipetrol.com', // email distinto — se ignora
        'Custodio' => 'Luis Guillermo Oviedo',
    ]]);

    $report = app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['created'])->toBe(1);
    expect($report['entities_created']['users'])->toBe(0); // no crea stub, encontró por cédula

    $asset = Asset::where('asset_tag', 'TAG-100')->first();
    expect($asset->user_id)->toBe($existing->id);
});

it('crea stub Azure pending cuando el custodio no existe y email es corporativo', function () {
    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-101',
        'Tipo Activo' => 'desktop',
        'Estado' => 'active',
        'Identificacion' => '9999999',
        'Correo' => 'nuevo.empleado@confipetrol.com',
        'Custodio' => 'Nuevo Empleado',
    ]]);

    $report = app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['entities_created']['users'])->toBe(1);

    $custodian = User::where('identification', '9999999')->first();
    expect($custodian->is_azure_pending)->toBeTrue();
    expect($custodian->email)->toBe('nuevo.empleado@confipetrol.com');
    expect($custodian->hasRole('usuario_final'))->toBeTrue();

    $asset = Asset::where('asset_tag', 'TAG-101')->first();
    expect($asset->user_id)->toBe($custodian->id);
});

it('crea stub local con password de cedula cuando el email no es corporativo', function () {
    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-102',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Identificacion' => '1234567890',
        'Correo' => 'contratista@externo.com',
        'Custodio' => 'Contratista X',
    ]]);

    app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    $custodian = User::where('identification', '1234567890')->first();
    expect($custodian->is_azure_pending)->toBeFalse();
    expect($custodian->password_must_change)->toBeTrue();
    expect(Hash::check('12345678', $custodian->password))->toBeTrue();
});

it('sin email genera email sintetico basado en cedula', function () {
    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-103',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Identificacion' => '5555555',
        'Custodio' => 'Sin Email',
    ]]);

    app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    $custodian = User::where('identification', '5555555')->first();
    expect($custodian->email)->toBe('5555555@sin-email.local');
});

it('modo estricto reporta error si el custodio no existe', function () {
    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-104',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Identificacion' => '888888',
        'Custodio' => 'Fantasma',
    ]]);

    $report = app(InventoryImportService::class)
        ->importFromFile($path, dryRun: false, strictCustodian: true);
    unlink($path);

    expect($report['created'])->toBe(0);
    expect($report['errors'])->toHaveCount(1);
    expect($report['errors'][0]['message'])->toContain('Modo estricto');
    expect(User::where('identification', '888888')->exists())->toBeFalse();
    expect(Asset::where('asset_tag', 'TAG-104')->exists())->toBeFalse();
});

it('actualiza asset existente por TAG sin duplicar', function () {
    $original = Asset::factory()->create([
        'asset_tag' => 'TAG-105',
        'model' => 'Modelo Viejo',
        'type' => 'laptop',
    ]);

    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-105',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Modelo' => 'Modelo Nuevo',
    ]]);

    $report = app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['created'])->toBe(0);
    expect($report['updated'])->toBe(1);
    expect(Asset::where('asset_tag', 'TAG-105')->count())->toBe(1);
    expect($original->fresh()->model)->toBe('Modelo Nuevo');
});

it('normaliza tipos en español a los enums validos', function () {
    $path = generateInventoryXlsx([
        ['TAG' => 'A1', 'Tipo Activo' => 'PC', 'Estado' => 'active'],
        ['TAG' => 'A2', 'Tipo Activo' => 'Portatil', 'Estado' => 'active'],
        ['TAG' => 'A3', 'Tipo Activo' => 'Impresora', 'Estado' => 'active'],
        ['TAG' => 'A4', 'Tipo Activo' => 'Antena', 'Estado' => 'active'],
    ]);

    app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    expect(Asset::find(Asset::where('asset_tag', 'A1')->first()->id)->type)->toBe('desktop');
    expect(Asset::where('asset_tag', 'A2')->first()->type)->toBe('laptop');
    expect(Asset::where('asset_tag', 'A3')->first()->type)->toBe('printer');
    expect(Asset::where('asset_tag', 'A4')->first()->type)->toBe('antenna');
});

it('no cambia rol ni password de custodio existente', function () {
    Role::findOrCreate('agente_soporte', 'web');
    $existing = User::factory()->create([
        'identification' => '1111',
        'password' => Hash::make('password-real-conocido'),
    ]);
    $existing->syncRoles(['agente_soporte']);

    $path = generateInventoryXlsx([[
        'TAG' => 'TAG-200',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
        'Identificacion' => '1111',
        'Correo' => 'nuevo@confipetrol.com',
    ]]);

    app(InventoryImportService::class)->importFromFile($path);
    unlink($path);

    $existing->refresh();
    // Rol NO cambia — el importer nunca reasigna.
    expect($existing->hasRole('agente_soporte'))->toBeTrue();
    expect($existing->hasRole('usuario_final'))->toBeFalse();
    // Password NO cambia.
    expect(Hash::check('password-real-conocido', $existing->password))->toBeTrue();
});

it('template service v2 tiene 21 columnas + hoja instrucciones', function () {
    $binary = app(InventoryTemplateService::class)->toBinary();
    $tmp = tempnam(sys_get_temp_dir(), 'inv_tpl_').'.xlsx';
    file_put_contents($tmp, $binary);
    $sp = IOFactory::load($tmp);
    unlink($tmp);

    $data = $sp->getSheetByName('Inventario');
    expect($data)->not->toBeNull();
    // Header row: A1='TAG', H1='Identificacion' (columna clave).
    expect($data->getCell('A1')->getValue())->toBe('TAG');
    expect($data->getCell('H1')->getValue())->toBe('Identificacion');

    // Ya no debe existir Ultimo Mtto, Mtto Dias, Responsable (removidos).
    $allHeaders = [];
    for ($col = 1; $col <= 25; $col++) {
        $v = $data->getCellByColumnAndRow($col, 1)->getValue();
        if ($v) {
            $allHeaders[] = $v;
        }
    }
    expect($allHeaders)->not->toContain('Ultimo Mtto');
    expect($allHeaders)->not->toContain('Mtto Dias');
    expect($allHeaders)->not->toContain('Responsable');

    expect($sp->getSheetByName('Instrucciones'))->not->toBeNull();
});

it('dry-run no persiste cambios', function () {
    $path = generateInventoryXlsx([[
        'TAG' => 'DRYRUN-1',
        'Tipo Activo' => 'laptop',
        'Estado' => 'active',
    ]]);

    $report = app(InventoryImportService::class)->importFromFile($path, dryRun: true);
    unlink($path);

    expect($report['created'])->toBe(1);
    expect(Asset::where('asset_tag', 'DRYRUN-1')->exists())->toBeFalse();
});
