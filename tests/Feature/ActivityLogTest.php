<?php

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\Department;
use App\Models\TicketComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Queue::fake();
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
});

it('registra inicio y cierre de sesión con la IP', function () {
    $user = User::factory()->create();
    $user->assignRole('usuario_final');

    Auth::login($user);
    Auth::logout();

    $login = Activity::inLog('acceso')->where('event', 'login')->first();

    expect($login)->not->toBeNull()
        ->and($login->causer_id)->toBe($user->id)
        ->and($login->properties->get('ip'))->toBe('127.0.0.1')
        ->and(Activity::inLog('acceso')->where('event', 'logout')->count())->toBe(1);
});

it('registra intentos fallidos sin guardar la contraseña', function () {
    event(new Failed('web', null, ['email' => 'nadie@confipetrol.com', 'password' => 'secreta']));

    $failed = Activity::inLog('acceso')->where('event', 'failed')->first();

    expect($failed->properties->get('email'))->toBe('nadie@confipetrol.com')
        ->and($failed->properties->has('password'))->toBeFalse();
});

it('registra lo que hace un agente con su usuario como autor', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agente_soporte');
    $this->actingAs($agent);

    $comment = TicketComment::factory()->create(['user_id' => $agent->id]);
    $comment->update(['body' => 'Respuesta corregida']);

    $activities = Activity::inLog('tickets')->forSubject($comment)->get();

    expect($activities->pluck('event')->all())->toContain('created', 'updated')
        ->and($activities->every(fn (Activity $a) => $a->causer_id === $agent->id))->toBeTrue();
});

it('no guarda la contraseña al editar un usuario', function () {
    $this->actingAs(User::factory()->create());
    $user = User::factory()->create();

    $user->update(['password' => 'otra-clave-123', 'name' => 'Nombre nuevo']);

    $changes = Activity::inLog('usuarios')->forSubject($user)->where('event', 'updated')->first()->attribute_changes;

    expect($changes->get('attributes'))->toHaveKey('name')
        ->and($changes->get('attributes'))->not->toHaveKey('password');
});

it('el coordinador y el admin ven la bitácora; el agente no', function (string $role, bool $allowed) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    expect(ActivityLogResource::canAccess())->toBe($allowed);
})->with([
    ['super_admin', true],
    ['admin', true],
    ['supervisor_soporte', true],
    ['agente_soporte', false],
    ['usuario_final', false],
]);

it('filtra la bitácora por usuario, acción y módulo', function () {
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $coordinator = User::factory()->create();
    $coordinator->assignRole('supervisor_soporte');

    $agent = User::factory()->create(['name' => 'Agente Revisado']);
    $agent->assignRole('agente_soporte');
    $other = User::factory()->create();

    Auth::login($agent);
    Auth::login($other);
    $this->actingAs($agent);
    $comment = TicketComment::factory()->create(['user_id' => $agent->id]);

    $this->actingAs($coordinator);

    $agentLogin = Activity::where('event', 'login')->where('causer_id', $agent->id)->first();
    $otherLogin = Activity::where('event', 'login')->where('causer_id', $other->id)->first();
    $commentCreated = Activity::forSubject($comment)->first();

    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$agentLogin, $otherLogin, $commentCreated])
        ->filterTable('causer', $agent->id)
        ->assertCanSeeTableRecords([$agentLogin, $commentCreated])
        ->assertCanNotSeeTableRecords([$otherLogin])
        ->filterTable('event', ['login'])
        ->assertCanSeeTableRecords([$agentLogin])
        ->assertCanNotSeeTableRecords([$commentCreated])
        ->removeTableFilters()
        ->filterTable('log_name', ['tickets'])
        ->assertCanSeeTableRecords([$commentCreated])
        ->assertCanNotSeeTableRecords([$agentLogin])
        ->removeTableFilters()
        ->filterTable('role', ['agente_soporte'])
        ->assertCanSeeTableRecords([$agentLogin])
        ->assertCanNotSeeTableRecords([$otherLogin]);
});

it('muestra el detalle de cambios de un registro', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $department = Department::factory()->create(['name' => 'Antes']);
    $department->update(['name' => 'Después']);
    $activity = Activity::forSubject($department)->where('event', 'updated')->first();

    Livewire::test(ListActivityLogs::class)
        ->assertTableActionVisible('details', $activity);

    $html = view('filament.activity-logs.details', ['activity' => $activity, 'subject' => 'Departamento Después'])->render();

    expect($html)->toContain('Antes')->toContain('Después')->toContain($admin->name);
});
