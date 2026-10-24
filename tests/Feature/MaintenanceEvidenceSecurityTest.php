<?php

use App\Models\Asset;
use App\Models\Department;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }
    // Fake persistente para toda la ejecución del test.
    Storage::fake('local');
});

function createEvidence(?ScheduledMaintenance $mto = null): array
{
    $mto = $mto ?? ScheduledMaintenance::factory()->create();
    $file = UploadedFile::fake()->image('evidencia.jpg');
    $media = $mto->addMedia($file)->toMediaCollection('evidence');

    Storage::disk($media->disk)->put(
        $media->id.'/'.$media->file_name,
        'contenido de prueba',
    );

    return [$mto, $media];
}

it('la colección "evidence" usa el disco "local" (no publico)', function () {
    [$mto, $media] = createEvidence();

    expect($media->disk)->toBe('local');
});

it('admin puede descargar cualquier evidencia', function () {
    [$mto, $media] = createEvidence();

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('maintenance.evidence.download', $media))
        ->assertOk();
});

it('supervisor del mismo depto puede descargar', function () {
    $dept = Department::factory()->create();
    $asset = Asset::factory()->create(['department_id' => $dept->id]);
    $mto = ScheduledMaintenance::factory()->create(['asset_id' => $asset->id]);
    [$mto, $media] = createEvidence($mto);

    $supervisor = User::factory()->create(['department_id' => $dept->id]);
    $supervisor->assignRole('supervisor_soporte');

    $this->actingAs($supervisor)
        ->get(route('maintenance.evidence.download', $media))
        ->assertOk();
});

it('supervisor de OTRO depto NO puede descargar', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $asset = Asset::factory()->create(['department_id' => $deptA->id]);
    $mto = ScheduledMaintenance::factory()->create(['asset_id' => $asset->id]);
    [$mto, $media] = createEvidence($mto);

    $supervisor = User::factory()->create(['department_id' => $deptB->id]);
    $supervisor->assignRole('supervisor_soporte');

    $this->actingAs($supervisor)
        ->get(route('maintenance.evidence.download', $media))
        ->assertForbidden();
});

it('agente solo puede descargar evidencia que él subió', function () {
    $agente = User::factory()->create();
    $agente->assignRole('agente_soporte');

    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agente_soporte');

    $mioMto = ScheduledMaintenance::factory()->create(['agent_id' => $agente->id]);
    [$mio, $mioMedia] = createEvidence($mioMto);

    $otherMto = ScheduledMaintenance::factory()->create(['agent_id' => $otherAgent->id]);
    [$other, $otherMedia] = createEvidence($otherMto);

    $this->actingAs($agente);
    $this->get(route('maintenance.evidence.download', $mioMedia))->assertOk();
    $this->get(route('maintenance.evidence.download', $otherMedia))->assertForbidden();
});

it('invitado no puede descargar (redirect a login)', function () {
    [$mto, $media] = createEvidence();

    $this->get(route('maintenance.evidence.download', $media))
        ->assertRedirect(route('login'));
});

it('rechaza si el media no es de la colección "evidence"', function () {
    // Si por alguna razón alguien envía un media de otra colección,
    // el controller debe rechazar con 404.
    Storage::fake('local');
    $mto = ScheduledMaintenance::factory()->create();
    $file = UploadedFile::fake()->image('otro.jpg');
    $media = $mto->addMedia($file)->toMediaCollection('default');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)
        ->get(route('maintenance.evidence.download', $media))
        ->assertNotFound();
});
