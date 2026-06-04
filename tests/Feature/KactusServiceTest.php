<?php

use App\DTOs\KactusEmployee;
use App\Models\Department;
use App\Models\User;
use App\Services\KactusService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('kactus.base_url', 'https://kactus.test/api');
    config()->set('kactus.api_key', 'test-key');
    config()->set('kactus.default_role', 'usuario_final');
    config()->set('kactus.on_terminate', 'deactivate');
    config()->set('kactus.department_map', []);

    Role::firstOrCreate(['name' => 'usuario_final', 'guard_name' => 'web']);
});

it('crea un user nuevo desde un payload Kactus', function () {
    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-001',
        'document_number' => '12345678',
        'first_name' => 'Luis',
        'last_name' => 'Oviedo',
        'email' => 'luis.oviedo.kactus@confipetrol.com',
        'position' => 'Líder TI',
        'phone' => '3001234567',
        'department' => 'Tecnologia',
        'status' => 'active',
    ]);

    $user = app(KactusService::class)->syncToUser($emp);

    expect($user->exists)->toBeTrue()
        ->and($user->kactus_employee_id)->toBe('K-001')
        ->and($user->identification)->toBe('12345678')
        ->and($user->name)->toBe('Luis Oviedo')
        ->and($user->email)->toBe('luis.oviedo.kactus@confipetrol.com')
        ->and($user->employment_status)->toBe('active')
        ->and($user->hasRole('usuario_final'))->toBeTrue();
});

it('matchea user existente por kactus_employee_id sin duplicar', function () {
    User::factory()->create([
        'email' => 'existente@confipetrol.com',
        'kactus_employee_id' => 'K-002',
        'name' => 'Nombre Viejo',
    ]);

    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-002',
        'first_name' => 'Nombre',
        'last_name' => 'Nuevo',
        'email' => 'existente@confipetrol.com',
        'status' => 'active',
    ]);

    app(KactusService::class)->syncToUser($emp);

    expect(User::where('kactus_employee_id', 'K-002')->count())->toBe(1)
        ->and(User::where('kactus_employee_id', 'K-002')->value('name'))->toBe('Nombre Nuevo');
});

it('adopta un user manual existente matcheando por identification', function () {
    $existing = User::factory()->create([
        'identification' => '99999999',
        'kactus_employee_id' => null,
        'name' => 'Creado a mano',
    ]);

    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-003',
        'document_number' => '99999999',
        'first_name' => 'Creado',
        'last_name' => 'A Mano',
        'status' => 'active',
    ]);

    app(KactusService::class)->syncToUser($emp);

    $existing->refresh();
    expect($existing->kactus_employee_id)->toBe('K-003')
        ->and(User::where('identification', '99999999')->count())->toBe(1);
});

it('marca como terminated y bloquea login cuando Kactus lo da de baja', function () {
    User::factory()->create([
        'email' => 'retirado@confipetrol.com',
        'kactus_employee_id' => 'K-004',
        'password' => bcrypt('clavePrevia'),
    ]);

    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-004',
        'first_name' => 'Ya',
        'last_name' => 'Retirado',
        'email' => 'retirado@confipetrol.com',
        'status' => 'terminated',
        'terminated_at' => '2026-05-01',
    ]);

    app(KactusService::class)->syncToUser($emp);

    $user = User::where('kactus_employee_id', 'K-004')->first();
    expect($user->employment_status)->toBe('terminated')
        ->and(Hash::check('clavePrevia', $user->password))->toBeFalse();
});

it('asigna department_id usando el mapping de config', function () {
    $dept = Department::factory()->create(['name' => 'Tecnologia Local']);
    config()->set('kactus.department_map', ['IT Department' => $dept->id]);

    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-005',
        'document_number' => '11111111',
        'first_name' => 'Mapped',
        'last_name' => 'User',
        'email' => 'mapped@confipetrol.com',
        'department' => 'IT Department',
        'status' => 'active',
    ]);

    $user = app(KactusService::class)->syncToUser($emp);

    expect($user->department_id)->toBe($dept->id);
});

it('asigna department_id buscando por nombre cuando no hay mapping', function () {
    $dept = Department::factory()->create(['name' => 'Soporte Kactus']);

    $emp = KactusEmployee::fromKactusPayload([
        'employee_id' => 'K-006',
        'document_number' => '22222222',
        'first_name' => 'No',
        'last_name' => 'Map',
        'email' => 'nomap@confipetrol.com',
        'department' => 'Soporte Kactus',
        'status' => 'active',
    ]);

    $user = app(KactusService::class)->syncToUser($emp);

    expect($user->department_id)->toBe($dept->id);
});

it('fetchEmployee retorna null cuando la API responde 404', function () {
    Http::fake([
        'https://kactus.test/api/employees/*' => Http::response(['error' => 'not found'], 404),
    ]);

    $result = app(KactusService::class)->fetchEmployee('K-NOPE');

    expect($result)->toBeNull();
});

it('fetchEmployee parsea correctamente un 200', function () {
    Http::fake([
        'https://kactus.test/api/employees/K-007' => Http::response([
            'employee_id' => 'K-007',
            'document_number' => '33333333',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@confipetrol.com',
            'status' => 'active',
        ], 200),
    ]);

    $emp = app(KactusService::class)->fetchEmployee('K-007');

    expect($emp)->not->toBeNull()
        ->and($emp->kactusId)->toBe('K-007')
        ->and($emp->name)->toBe('Jane Doe');
});

it('syncBatch resume created/updated/deactivated correctamente', function () {
    User::factory()->create(['kactus_employee_id' => 'K-100', 'email' => 'u100@x.com']);

    $employees = [
        KactusEmployee::fromKactusPayload([
            'employee_id' => 'K-100',
            'first_name' => 'Actualizado',
            'last_name' => 'User',
            'email' => 'u100@x.com',
            'status' => 'active',
        ]),
        KactusEmployee::fromKactusPayload([
            'employee_id' => 'K-101',
            'document_number' => '44444444',
            'first_name' => 'Nuevo',
            'last_name' => 'User',
            'email' => 'u101@x.com',
            'status' => 'active',
        ]),
        KactusEmployee::fromKactusPayload([
            'employee_id' => 'K-102',
            'document_number' => '55555555',
            'first_name' => 'Termi',
            'last_name' => 'Nated',
            'email' => 'u102@x.com',
            'status' => 'terminated',
        ]),
    ];

    $result = app(KactusService::class)->syncBatch($employees);

    expect($result->updated)->toBe(1)
        ->and($result->created)->toBe(1)
        ->and($result->deactivated)->toBe(1)
        ->and($result->hasErrors())->toBeFalse();
});
