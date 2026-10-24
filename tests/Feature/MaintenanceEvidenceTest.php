<?php

use App\Models\ScheduledMaintenance;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

it('ScheduledMaintenance implementa el contrato HasMedia', function () {
    expect(ScheduledMaintenance::class)->toImplement(HasMedia::class);
});

it('registra la colección "evidence" con MIMEs esperados', function () {
    $maintenance = ScheduledMaintenance::factory()->create();

    $collections = $maintenance->getRegisteredMediaCollections();
    $names = $collections->pluck('name')->all();

    expect($names)->toContain('evidence');

    $evidence = $collections->firstWhere('name', 'evidence');
    expect($evidence)->not->toBeNull();
    expect($evidence->acceptsMimeTypes)->toContain('application/pdf')
        ->and($evidence->acceptsMimeTypes)->toContain('image/jpeg')
        ->and($evidence->acceptsMimeTypes)->toContain('image/png');
});

it('permite adjuntar una imagen como evidencia', function () {
    Storage::fake('public');

    $maintenance = ScheduledMaintenance::factory()->create();

    $jpg = UploadedFile::fake()->image('evidencia-mto.jpg');

    $maintenance->addMedia($jpg)->toMediaCollection('evidence');

    expect($maintenance->getMedia('evidence'))->toHaveCount(1);
    expect($maintenance->getMedia('evidence')->first()->file_name)->toBe('evidencia-mto.jpg');
});

it('rechaza adjuntos de tipos no permitidos', function () {
    Storage::fake('public');

    $maintenance = ScheduledMaintenance::factory()->create();

    $exe = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

    expect(fn () => $maintenance->addMedia($exe)->toMediaCollection('evidence'))
        ->toThrow(FileUnacceptableForCollection::class);
});
