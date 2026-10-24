<?php

use App\Filament\Soporte\Pages\UnifiedSurveysReport;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceSurvey;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }
});

function pageBuildRows(UnifiedSurveysReport $p): Collection
{
    $m = new ReflectionMethod($p, 'buildRows');
    $m->setAccessible(true);

    return $m->invoke($p);
}

it('supervisor_soporte solo ve encuestas de tickets de su depto', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $tA = Ticket::factory()->create(['department_id' => $deptA->id]);
    $tB = Ticket::factory()->create(['department_id' => $deptB->id]);

    SatisfactionSurvey::create([
        'ticket_id' => $tA->id,
        'user_id' => $tA->requester_id,
        'rating' => 5,
        'responded_at' => now()->subDay(),
    ]);
    SatisfactionSurvey::create([
        'ticket_id' => $tB->id,
        'user_id' => $tB->requester_id,
        'rating' => 3,
        'responded_at' => now()->subDay(),
    ]);

    $supervisor = User::factory()->create(['department_id' => $deptA->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);

    $rows = pageBuildRows(new UnifiedSurveysReport);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['related_label'])->toContain($tA->number);
});

it('supervisor_soporte solo ve encuestas de mantenimiento de activos de su depto', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $aA = Asset::factory()->create(['department_id' => $deptA->id, 'asset_tag' => 'A-MIO']);
    $aB = Asset::factory()->create(['department_id' => $deptB->id, 'asset_tag' => 'A-AJENO']);

    $user = User::factory()->create();
    MaintenanceSurvey::create([
        'asset_id' => $aA->id,
        'user_id' => $user->id,
        'rating' => 5,
        'responded_at' => now()->subDay(),
    ]);
    MaintenanceSurvey::create([
        'asset_id' => $aB->id,
        'user_id' => $user->id,
        'rating' => 3,
        'responded_at' => now()->subDay(),
    ]);

    $supervisor = User::factory()->create(['department_id' => $deptA->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);

    $rows = pageBuildRows(new UnifiedSurveysReport);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['related_label'])->toBe('A-MIO');
});

it('admin ve TODAS las encuestas sin importar depto', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $tA = Ticket::factory()->create(['department_id' => $deptA->id]);
    $tB = Ticket::factory()->create(['department_id' => $deptB->id]);

    SatisfactionSurvey::create(['ticket_id' => $tA->id, 'user_id' => $tA->requester_id, 'rating' => 5, 'responded_at' => now()->subDay()]);
    SatisfactionSurvey::create(['ticket_id' => $tB->id, 'user_id' => $tB->requester_id, 'rating' => 4, 'responded_at' => now()->subDay()]);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    expect(pageBuildRows(new UnifiedSurveysReport))->toHaveCount(2);
});

it('supervisor sin department_id no ve nada', function () {
    $deptA = Department::factory()->create();
    $t = Ticket::factory()->create(['department_id' => $deptA->id]);
    SatisfactionSurvey::create(['ticket_id' => $t->id, 'user_id' => $t->requester_id, 'rating' => 5, 'responded_at' => now()->subDay()]);

    $supervisor = User::factory()->create(['department_id' => null]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);

    expect(pageBuildRows(new UnifiedSurveysReport))->toHaveCount(0);
});
