<?php

use App\Enums\TicketStatus;
use App\Filament\Soporte\Widgets\AgentRankingWidget;
use App\Models\Department;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor_soporte', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'agente_soporte', 'guard_name' => 'web']);
});

function buildRankingQuery(): Builder
{
    $widget = new AgentRankingWidget;
    $method = new ReflectionMethod($widget, 'buildQuery');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

it('cuenta tickets resueltos en los últimos 30 días por agente', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $dept = Department::create(['name' => 'TI', 'slug' => 'ti']);
    $agent = User::factory()->create(['department_id' => $dept->id]);
    $agent->assignRole('agente_soporte');

    Ticket::factory()->count(3)->create([
        'assigned_to_id' => $agent->id,
        'status' => TicketStatus::Resuelto,
        'resolved_at' => now()->subDays(5),
    ]);
    Ticket::factory()->create([
        'assigned_to_id' => $agent->id,
        'status' => TicketStatus::Resuelto,
        'resolved_at' => now()->subDays(40),
    ]);

    $row = buildRankingQuery()->where('users.id', $agent->id)->first();

    expect((int) $row->resolved_count)->toBe(3);
});

it('calcula CSAT promedio del agente', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $dept = Department::create(['name' => 'TI', 'slug' => 'ti']);
    $agent = User::factory()->create(['department_id' => $dept->id]);
    $agent->assignRole('agente_soporte');

    $t1 = Ticket::factory()->create(['assigned_to_id' => $agent->id, 'status' => TicketStatus::Cerrado, 'resolved_at' => now()->subDays(3)]);
    $t2 = Ticket::factory()->create(['assigned_to_id' => $agent->id, 'status' => TicketStatus::Cerrado, 'resolved_at' => now()->subDays(2)]);

    SatisfactionSurvey::create(['ticket_id' => $t1->id, 'user_id' => $t1->requester_id, 'rating' => 5, 'responded_at' => now()->subDays(1)]);
    SatisfactionSurvey::create(['ticket_id' => $t2->id, 'user_id' => $t2->requester_id, 'rating' => 3, 'responded_at' => now()->subDays(1)]);

    $row = buildRankingQuery()->where('users.id', $agent->id)->first();

    expect((float) $row->csat_avg)->toBe(4.0);
});

it('supervisor solo ve agentes de su propio departamento', function () {
    $deptTi = Department::create(['name' => 'TI', 'slug' => 'ti']);
    $deptRrhh = Department::create(['name' => 'RRHH', 'slug' => 'rrhh']);

    $supervisor = User::factory()->create(['department_id' => $deptTi->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);

    $agTi = User::factory()->create(['name' => 'AgenteTI', 'department_id' => $deptTi->id]);
    $agTi->assignRole('agente_soporte');
    $agRrhh = User::factory()->create(['name' => 'AgenteRRHH', 'department_id' => $deptRrhh->id]);
    $agRrhh->assignRole('agente_soporte');

    $names = buildRankingQuery()->pluck('users.name')->all();

    expect($names)->toContain('AgenteTI')
        ->and($names)->not->toContain('AgenteRRHH');
});

it('admin ve todos los agentes sin importar el depto', function () {
    $deptTi = Department::create(['name' => 'TI', 'slug' => 'ti']);
    $deptRrhh = Department::create(['name' => 'RRHH', 'slug' => 'rrhh']);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $agTi = User::factory()->create(['name' => 'AgT', 'department_id' => $deptTi->id]);
    $agTi->assignRole('agente_soporte');
    $agRrhh = User::factory()->create(['name' => 'AgR', 'department_id' => $deptRrhh->id]);
    $agRrhh->assignRole('agente_soporte');

    $names = buildRankingQuery()->pluck('users.name')->all();

    expect($names)->toContain('AgT')->and($names)->toContain('AgR');
});

it('canView() rechaza usuarios sin rol de admin/supervisor', function () {
    $user = User::factory()->create();
    $user->assignRole('agente_soporte');
    $this->actingAs($user);

    expect(AgentRankingWidget::canView())->toBeFalse();
});
