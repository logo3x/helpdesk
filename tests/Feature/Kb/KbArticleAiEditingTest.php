<?php

use App\Filament\Soporte\Resources\KbArticles\Pages\CreateKbArticle;
use App\Filament\Soporte\Resources\KbArticles\Pages\EditKbArticle;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\LlmService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    config(['services.llm.kb_drafting_enabled' => true]);

    $this->department = Department::factory()->create();
    $this->supervisor = User::factory()->create(['department_id' => $this->department->id]);
    $this->supervisor->assignRole('supervisor_soporte');
    $this->actingAs($this->supervisor);

    $this->article = KbArticle::factory()->create([
        'title' => 'Instalar Windows App',
        'slug' => 'instalar-windows-app',
        'body' => "## Paso 1\nAbre **Microsoft Store**.",
        'department_id' => $this->department->id,
    ]);
});

it('muestra la vista previa del Markdown interpretado al editar', function () {
    Livewire::test(EditKbArticle::class, ['record' => $this->article->getRouteKey()])
        ->assertSee('Vista previa')
        ->assertSeeHtml('<h2>Paso 1</h2>')
        ->assertSeeHtml('<strong>Microsoft Store</strong>');
});

it('la vista previa no ejecuta HTML incrustado', function () {
    $this->article->update(['body' => "Hola <script>alert('x')</script>"]);

    Livewire::test(EditKbArticle::class, ['record' => $this->article->getRouteKey()])
        ->assertDontSeeHtml("<script>alert('x')</script>");
});

it('al editar se puede refinar el cuerpo con IA', function () {
    $this->mock(LlmService::class, fn ($mock) => $mock->shouldReceive('chat')->andReturn("## Paso 1\nAbre la **Microsoft Store** desde Inicio."));

    Livewire::test(EditKbArticle::class, ['record' => $this->article->getRouteKey()])
        ->callAction('aiRefine', ['preset' => 'end_user'])
        ->assertHasNoActionErrors()
        ->assertSchemaStateSet(['body' => "## Paso 1\nAbre la **Microsoft Store** desde Inicio."]);
});

it('al editar solo aparece Refinar con IA, no Redactar con IA', function () {
    Livewire::test(EditKbArticle::class, ['record' => $this->article->getRouteKey()])
        ->assertActionVisible('aiRefine')
        ->assertActionDoesNotExist('draftWithAi');
});

it('al crear se sigue pudiendo redactar con IA', function () {
    $this->mock(LlmService::class, fn ($mock) => $mock->shouldReceive('draftKbArticle')->andReturn([
        'title' => 'Reiniciar router',
        'body' => '1. Reinicia el router.',
    ]));

    Livewire::test(CreateKbArticle::class)
        ->callAction('draftWithAi', ['natural_language' => 'cuando no hay wifi reiniciar el router del piso', 'tone' => 'formal'])
        ->assertSchemaStateSet(['title' => 'Reiniciar router', 'slug' => 'reiniciar-router']);
});
