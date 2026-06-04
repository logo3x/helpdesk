<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('kactus.enabled', true);
    config()->set('kactus.base_url', 'https://kactus.test/api');
    config()->set('kactus.api_key', 'test-key');
    config()->set('kactus.default_role', 'usuario_final');
    config()->set('kactus.on_terminate', 'deactivate');
    Role::firstOrCreate(['name' => 'usuario_final', 'guard_name' => 'web']);
});

it('no hace nada si KACTUS_ENABLED=false', function () {
    config()->set('kactus.enabled', false);

    $this->artisan('kactus:sync')
        ->expectsOutputToContain('deshabilitado')
        ->assertExitCode(0);
});

it('sincroniza un único empleado con --user', function () {
    Http::fake([
        'https://kactus.test/api/employees/K-CMD-1' => Http::response([
            'employee_id' => 'K-CMD-1',
            'document_number' => '88888888',
            'first_name' => 'Cmd',
            'last_name' => 'Test',
            'email' => 'cmdtest@confipetrol.com',
            'status' => 'active',
        ], 200),
    ]);

    $this->artisan('kactus:sync', ['--user' => 'K-CMD-1'])
        ->assertExitCode(0);

    expect(User::where('kactus_employee_id', 'K-CMD-1')->exists())->toBeTrue();
});

it('falla con código 1 cuando el empleado --user no existe en Kactus', function () {
    Http::fake([
        'https://kactus.test/api/employees/*' => Http::response(['error' => 'nf'], 404),
    ]);

    $this->artisan('kactus:sync', ['--user' => 'K-NOPE'])
        ->assertExitCode(1);
});

it('--dry-run no persiste cambios', function () {
    Http::fake([
        'https://kactus.test/api/employees/K-DRY' => Http::response([
            'employee_id' => 'K-DRY',
            'document_number' => '11223344',
            'first_name' => 'Dry',
            'last_name' => 'Run',
            'email' => 'dry@confipetrol.com',
            'status' => 'active',
        ], 200),
    ]);

    $this->artisan('kactus:sync', ['--user' => 'K-DRY', '--dry-run' => true])
        ->assertExitCode(0);

    expect(User::where('kactus_employee_id', 'K-DRY')->exists())->toBeFalse();
});

it('sync masivo procesa la lista paginada', function () {
    Http::fake([
        'https://kactus.test/api/employees*' => Http::sequence()
            ->push([
                'data' => [
                    ['employee_id' => 'K-M1', 'document_number' => '1', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com', 'status' => 'active'],
                    ['employee_id' => 'K-M2', 'document_number' => '2', 'first_name' => 'C', 'last_name' => 'D', 'email' => 'c@d.com', 'status' => 'active'],
                ],
                'meta' => ['has_more' => false],
            ], 200),
    ]);

    $this->artisan('kactus:sync')->assertExitCode(0);

    expect(User::whereIn('kactus_employee_id', ['K-M1', 'K-M2'])->count())->toBe(2);
});
