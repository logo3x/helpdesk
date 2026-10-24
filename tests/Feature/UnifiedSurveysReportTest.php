<?php

use App\Filament\Soporte\Pages\UnifiedSurveysReport;
use App\Models\Asset;
use App\Models\MaintenanceSurvey;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'agente_soporte', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

function invokeBuildRows(UnifiedSurveysReport $page): Collection
{
    $m = new ReflectionMethod($page, 'buildRows');
    $m->setAccessible(true);

    return $m->invoke($page);
}

it('combina encuestas de tickets y de mantenimientos en una sola lista', function () {
    $this->actingAs($this->admin);

    $ticket = Ticket::factory()->create();
    SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
        'rating' => 5,
        'responded_at' => now()->subDay(),
    ]);

    $asset = Asset::factory()->create(['asset_tag' => 'A-100']);
    MaintenanceSurvey::create([
        'asset_id' => $asset->id,
        'user_id' => $this->admin->id,
        'rating' => 3,
        'responded_at' => now()->subDay(),
    ]);

    $page = new UnifiedSurveysReport;
    $rows = invokeBuildRows($page);

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('source')->sort()->values()->all())->toBe(['maintenance', 'ticket']);
});

it('filtra solo encuestas de ticket cuando source=ticket', function () {
    $this->actingAs($this->admin);

    $ticket = Ticket::factory()->create();
    SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
        'rating' => 5,
        'responded_at' => now()->subDay(),
    ]);

    $asset = Asset::factory()->create(['asset_tag' => 'A-200']);
    MaintenanceSurvey::create([
        'asset_id' => $asset->id,
        'user_id' => $this->admin->id,
        'rating' => 3,
        'responded_at' => now()->subDay(),
    ]);

    $page = new UnifiedSurveysReport;
    $page->source = 'ticket';
    $rows = invokeBuildRows($page);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['source'])->toBe('ticket');
});

it('filtra solo encuestas de mantenimiento cuando source=maintenance', function () {
    $this->actingAs($this->admin);

    $ticket = Ticket::factory()->create();
    SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
        'rating' => 5,
        'responded_at' => now()->subDay(),
    ]);

    $asset = Asset::factory()->create(['asset_tag' => 'A-300']);
    MaintenanceSurvey::create([
        'asset_id' => $asset->id,
        'user_id' => $this->admin->id,
        'rating' => 3,
        'responded_at' => now()->subDay(),
    ]);

    $page = new UnifiedSurveysReport;
    $page->source = 'maintenance';
    $rows = invokeBuildRows($page);

    expect($rows)->toHaveCount(1);
    expect($rows->first()['source'])->toBe('maintenance');
});

it('respeta la ventana de días', function () {
    $this->actingAs($this->admin);

    $ticket = Ticket::factory()->create();
    $old = SatisfactionSurvey::create([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
        'rating' => 5,
        'responded_at' => now()->subDays(200),
    ]);
    $old->forceFill(['created_at' => now()->subDays(200)])->save();

    $page = new UnifiedSurveysReport;
    $page->window = '30';
    $rows = invokeBuildRows($page);

    expect($rows)->toBeEmpty();

    $page->window = '365';
    $rows = invokeBuildRows($page);

    expect($rows)->toHaveCount(1);
});

it('no es accesible para agentes sin rol de supervisor/admin', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agente_soporte');
    $this->actingAs($agent);

    expect(UnifiedSurveysReport::canAccess())->toBeFalse();
});

it('es accesible para super_admin', function () {
    $this->actingAs($this->admin);

    expect(UnifiedSurveysReport::canAccess())->toBeTrue();
});
