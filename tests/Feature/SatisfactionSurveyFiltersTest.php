<?php

use App\Filament\Resources\SatisfactionSurveys\Pages\ListSatisfactionSurveys;
use App\Filament\Resources\SatisfactionSurveys\Tables\SatisfactionSurveysTable;
use App\Filament\Resources\SatisfactionSurveys\Widgets\SurveyStatsWidget;
use App\Filament\Soporte\Resources\SatisfactionSurveys\Pages\ListSatisfactionSurveys as SoporteListSatisfactionSurveys;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);

    $this->agent = User::factory()->create(['name' => 'Laura Agente']);
    $this->otherAgent = User::factory()->create(['name' => 'Pedro Otro']);
});

function survey(array $attributes = [], ?User $agent = null): SatisfactionSurvey
{
    $ticket = Ticket::factory()->create(['assigned_to_id' => $agent?->id]);

    return SatisfactionSurvey::create(array_merge([
        'ticket_id' => $ticket->id,
        'user_id' => $ticket->requester_id,
    ], $attributes));
}

function answered(int $value): array
{
    return array_merge(
        ['rating' => $value, 'responded_at' => now()],
        array_fill_keys(array_keys(SatisfactionSurvey::DIMENSIONS), $value),
    );
}

it('filtra por origen: usuario, auto-positiva y pendiente', function () {
    $real = survey(answered(4), $this->agent);
    $auto = survey(['rating' => 5, 'responded_at' => now(), 'comment' => '(auto-positiva: cliente no respondió en 1 días)']);
    $pending = survey();

    Livewire::test(ListSatisfactionSurveys::class)
        ->assertCanSeeTableRecords([$real, $auto, $pending])
        ->filterTable('origin', ['origin' => 'user'])
        ->assertCanSeeTableRecords([$real])
        ->assertCanNotSeeTableRecords([$auto, $pending])
        ->filterTable('origin', ['origin' => 'auto'])
        ->assertCanSeeTableRecords([$auto])
        ->assertCanNotSeeTableRecords([$real, $pending])
        ->filterTable('origin', ['origin' => 'pending'])
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$real, $auto]);
});

it('filtra por agente, rango de calificación y preguntas bajas', function () {
    $good = survey(answered(5), $this->agent);
    $bad = survey(answered(2), $this->otherAgent);
    $mixed = survey(array_merge(answered(4), ['rating_time' => 1]), $this->agent);

    Livewire::test(ListSatisfactionSurveys::class)
        ->filterTable('agent', ['agent_id' => $this->agent->id])
        ->assertCanSeeTableRecords([$good, $mixed])
        ->assertCanNotSeeTableRecords([$bad])
        ->resetTableFilters()
        ->filterTable('rating_range', ['rating_max' => '2'])
        ->assertCanSeeTableRecords([$bad])
        ->assertCanNotSeeTableRecords([$good, $mixed])
        ->resetTableFilters()
        ->filterTable('low_dimension', ['isActive' => true])
        ->assertCanSeeTableRecords([$bad, $mixed])
        ->assertCanNotSeeTableRecords([$good]);
});

it('las estadísticas se calculan con los filtros de la tabla', function () {
    survey(answered(4), $this->agent);
    survey(answered(2), $this->otherAgent);
    survey(['rating' => 5, 'responded_at' => now(), 'comment' => '(auto-positiva: cliente no respondió en 1 días)']);

    Livewire::test(SurveyStatsWidget::class, [
        'tablePageClass' => ListSatisfactionSurveys::class,
        'tableFilters' => ['agent' => ['agent_id' => (string) $this->agent->id]],
    ])
        ->assertSee('4.00 / 5')
        ->assertSee('1 de 1 respondidas por el usuario');

    Livewire::test(SurveyStatsWidget::class, ['tablePageClass' => ListSatisfactionSurveys::class])
        ->assertSee('2 reales · 1 auto-positivas')
        ->assertSee('67%');
});

it('muestra el botón de ayuda y la página de soporte renderiza', function () {
    Livewire::test(ListSatisfactionSurveys::class)
        ->assertActionExists('surveyHelp')
        ->mountAction('surveyHelp')
        ->assertActionMounted('surveyHelp');

    $this->view('filament.satisfaction-surveys.help', ['autoPositiveDays' => 1])
        ->assertSee('Encuestas auto-positivas')
        ->assertSee('1 día,', false);

    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    Livewire::test(SoporteListSatisfactionSurveys::class)->assertOk();
});

it('identifica el origen de cada encuesta', function () {
    expect(SatisfactionSurveysTable::originOf(new SatisfactionSurvey))->toBe('Pendiente')
        ->and(SatisfactionSurveysTable::originOf(new SatisfactionSurvey(['responded_at' => now()])))->toBe('Usuario')
        ->and(SatisfactionSurveysTable::originOf(new SatisfactionSurvey([
            'responded_at' => now(),
            'comment' => 'ok (auto-positiva: cliente no respondió en 1 días)',
        ])))->toBe('Auto-positiva');
});
