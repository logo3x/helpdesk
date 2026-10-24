<?php

use App\Models\Asset;
use App\Models\Project;

it('filtra proyectos por gerencia derivada de los activos', function () {
    $pA = Project::create(['code' => 'P-A', 'name' => 'Proyecto Alpha', 'is_active' => true]);
    $pB = Project::create(['code' => 'P-B', 'name' => 'Proyecto Beta', 'is_active' => true]);
    $pC = Project::create(['code' => 'P-C', 'name' => 'Sin activos', 'is_active' => true]);

    Asset::factory()->create([
        'project_id' => $pA->id,
        'management_area' => 'HSEQ',
        'type' => 'laptop',
    ]);
    Asset::factory()->create([
        'project_id' => $pB->id,
        'management_area' => 'Operaciones',
        'type' => 'laptop',
    ]);

    $projectsForHSEQ = Project::query()
        ->where('is_active', true)
        ->whereHas('assets', fn ($q) => $q->where('management_area', 'HSEQ'))
        ->pluck('id')
        ->all();

    expect($projectsForHSEQ)->toContain($pA->id)
        ->and($projectsForHSEQ)->not->toContain($pB->id)
        ->and($projectsForHSEQ)->not->toContain($pC->id);
});

it('sin gerencia seleccionada devuelve todos los proyectos activos', function () {
    Project::create(['code' => 'ACT-1', 'name' => 'Activo 1', 'is_active' => true]);
    Project::create(['code' => 'ACT-2', 'name' => 'Activo 2', 'is_active' => true]);
    Project::create(['code' => 'INA-1', 'name' => 'Inactivo', 'is_active' => false]);

    $all = Project::query()
        ->where('is_active', true)
        ->pluck('code')
        ->all();

    expect($all)->toContain('ACT-1')
        ->and($all)->toContain('ACT-2')
        ->and($all)->not->toContain('INA-1');
});
