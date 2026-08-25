<?php

use App\Models\User;
use App\Services\PeopleImportService;
use App\Services\PeopleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Roles mínimos que el importer puede asignar.
    foreach ([
        'usuario_final', 'agente_soporte', 'supervisor_soporte',
        'tecnico_campo', 'editor_kb', 'admin', 'super_admin',
    ] as $r) {
        Role::findOrCreate($r, 'web');
    }
});

/**
 * Genera un .xlsx temporal con los datos que le pasés — usa el mismo
 * writer que el TemplateService pero con filas propias. Devuelve la
 * ruta absoluta al archivo (limpia al final del test).
 *
 * @param  array<int, array<string, mixed>>  $rows
 */
function generatePeopleXlsx(array $rows): string
{
    $sp = new Spreadsheet;
    $sheet = $sp->getActiveSheet();
    $sheet->setTitle('Personas');

    $headers = ['Identificacion', 'Nombre Completo', 'Email', 'Cargo', 'Departamento', 'Rol', 'Telefono', 'Gerencia'];
    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }

    foreach ($rows as $rowIndex => $row) {
        foreach ($headers as $i => $h) {
            $sheet->setCellValueExplicitByColumnAndRow(
                $i + 1,
                $rowIndex + 2,
                $row[$h] ?? '',
                DataType::TYPE_STRING,
            );
        }
    }

    $tmp = tempnam(sys_get_temp_dir(), 'people_').'.xlsx';
    (new Xlsx($sp))->save($tmp);

    return $tmp;
}

it('cuenta @confipetrol.com queda como Azure pending sin password utilizable', function () {
    $path = generatePeopleXlsx([[
        'Identificacion' => '1121898647',
        'Nombre Completo' => 'Luis Oviedo',
        'Email' => 'luis.oviedo@confipetrol.com',
        'Cargo' => 'Líder',
        'Departamento' => 'Tecnología',
        'Rol' => 'super_admin',
    ]]);

    $report = app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['created_azure'])->toBe(1);
    expect($report['created_local'])->toBe(0);

    $user = User::where('identification', '1121898647')->first();
    expect($user)->not->toBeNull();
    expect($user->is_azure_pending)->toBeTrue();
    expect($user->password_must_change)->toBeFalse();
    expect($user->email)->toBe('luis.oviedo@confipetrol.com');
    // El password es random 60 chars hasheado — inutilizable, pero no vacío.
    expect(Hash::check('1121898647', $user->password))->toBeFalse();
    expect($user->hasRole('super_admin'))->toBeTrue();
});

it('cuenta local usa primeros 8 digitos de cedula como password inicial', function () {
    $path = generatePeopleXlsx([[
        'Identificacion' => '1234567890',
        'Nombre Completo' => 'Juan Contratista',
        'Email' => 'juan@externo.com',
        'Rol' => 'usuario_final',
    ]]);

    $report = app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['created_local'])->toBe(1);
    expect($report['created_azure'])->toBe(0);

    $user = User::where('identification', '1234567890')->first();
    expect($user->is_azure_pending)->toBeFalse();
    expect($user->password_must_change)->toBeTrue();
    // Password inicial = primeros 8 dígitos: "12345678"
    expect(Hash::check('12345678', $user->password))->toBeTrue();
});

it('sin email tambien crea cuenta local', function () {
    $path = generatePeopleXlsx([[
        'Identificacion' => '99999999',
        'Nombre Completo' => 'Operario Sin Email',
    ]]);

    $report = app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['created_local'])->toBe(1);
    $user = User::where('identification', '99999999')->first();
    expect($user->is_azure_pending)->toBeFalse();
    expect(Hash::check('99999999', $user->password))->toBeTrue();
});

it('cedula ya existente actualiza sin cambiar password ni rol', function () {
    // Pre-crear un usuario con rol y password conocidos.
    $existing = User::factory()->create([
        'identification' => '1121898647',
        'name' => 'Nombre Original',
        'password' => Hash::make('super-secreto-original'),
        'is_azure_pending' => false,
        'password_must_change' => false,
    ]);
    $existing->syncRoles(['agente_soporte']);

    $path = generatePeopleXlsx([[
        'Identificacion' => '1121898647',
        'Nombre Completo' => 'Nombre Actualizado',
        'Email' => 'algo@confipetrol.com',
        'Rol' => 'super_admin', // intento de escalar — debe ignorarse en updates.
    ]]);

    $report = app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['updated'])->toBe(1);
    expect($report['created_azure'])->toBe(0);
    expect($report['created_local'])->toBe(0);

    $existing->refresh();
    expect($existing->name)->toBe('Nombre Actualizado');
    // Password NO cambia en updates.
    expect(Hash::check('super-secreto-original', $existing->password))->toBeTrue();
    // Rol NO cambia en updates (el panel manda).
    expect($existing->hasRole('agente_soporte'))->toBeTrue();
    expect($existing->hasRole('super_admin'))->toBeFalse();
});

it('rol invalido cae a usuario_final por default', function () {
    $path = generatePeopleXlsx([[
        'Identificacion' => '55555555',
        'Nombre Completo' => 'Rol Inventado',
        'Rol' => 'super_hacker_mode',
    ]]);

    app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    $user = User::where('identification', '55555555')->first();
    expect($user->hasRole('usuario_final'))->toBeTrue();
});

it('sin cedula omite la fila sin abortar', function () {
    $path = generatePeopleXlsx([
        ['Identificacion' => '', 'Nombre Completo' => 'Sin Cédula'],
        ['Identificacion' => '1111', 'Nombre Completo' => 'Con Cédula'],
    ]);

    $report = app(PeopleImportService::class)->importFromFile($path);
    unlink($path);

    expect($report['skipped'])->toBe(1);
    expect($report['created_local'])->toBe(1);
});

it('template service genera un xlsx valido con 2 hojas', function () {
    $binary = app(PeopleTemplateService::class)->toBinary();
    expect(strlen($binary))->toBeGreaterThan(1000);

    $tmp = tempnam(sys_get_temp_dir(), 'plantilla_').'.xlsx';
    file_put_contents($tmp, $binary);
    $sp = IOFactory::load($tmp);
    unlink($tmp);

    expect($sp->getSheetCount())->toBe(2);
    expect($sp->getSheetByName('Personas'))->not->toBeNull();
    expect($sp->getSheetByName('Instrucciones'))->not->toBeNull();

    // Header en la primera fila.
    expect($sp->getSheetByName('Personas')->getCell('A1')->getValue())->toBe('Identificacion');
});

it('dry-run no persiste cambios', function () {
    $path = generatePeopleXlsx([[
        'Identificacion' => '77777777',
        'Nombre Completo' => 'No Deberia Existir',
    ]]);

    $report = app(PeopleImportService::class)->importFromFile($path, dryRun: true);
    unlink($path);

    expect($report['created_local'])->toBe(1);
    expect(User::where('identification', '77777777')->exists())->toBeFalse();
});
