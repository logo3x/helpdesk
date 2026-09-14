<?php

use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Services\ConsolidadoIndicadoresExporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

it('genera un xlsx binario con la plantilla oficial', function () {
    $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary(2026);

    expect(strlen($binary))->toBeGreaterThan(1000);
    // Firma ZIP (todo .xlsx es un zip).
    expect(substr($binary, 0, 2))->toBe('PK');
});

it('llena la columna % Tickets Resueltos del mes con datos reales', function () {
    // Enero 2026: 10 tickets creados, 8 resueltos.
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 1, 15));

    Ticket::factory()->count(8)->create([
        'created_at' => CarbonImmutable::create(2026, 1, 5),
        'resolved_at' => CarbonImmutable::create(2026, 1, 10),
    ]);
    Ticket::factory()->count(2)->create([
        'created_at' => CarbonImmutable::create(2026, 1, 20),
        'resolved_at' => null,
    ]);

    $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary(2026);

    // Cargar el resultado y verificar C14 (fila enero de Tickets Resueltos)
    $tmp = tempnam(sys_get_temp_dir(), 'cons_').'.xlsx';
    file_put_contents($tmp, $binary);
    $sp = IOFactory::load($tmp);
    unlink($tmp);

    $value = $sp->getSheet(0)->getCell('C14')->getValue();
    expect($value)->toBe(0.8); // 8/10

    CarbonImmutable::setTestNow();
});

it('llena incidencias mayores con tickets de prioridad Crítica', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    Ticket::factory()->count(3)->create([
        'priority' => TicketPriority::Critica,
        'created_at' => CarbonImmutable::create(2026, 3, 5),
    ]);
    Ticket::factory()->count(5)->create([
        'priority' => TicketPriority::Media,
        'created_at' => CarbonImmutable::create(2026, 3, 5),
    ]);

    $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary(2026);

    $tmp = tempnam(sys_get_temp_dir(), 'cons_').'.xlsx';
    file_put_contents($tmp, $binary);
    $sp = IOFactory::load($tmp);
    unlink($tmp);

    // Fila 80 = marzo, columna C = incidencias mayores.
    expect($sp->getSheet(0)->getCell('C80')->getValue())->toBe(3);

    CarbonImmutable::setTestNow();
});

it('no rellena meses futuros del año en curso', function () {
    // Hoy = marzo 15, 2026. Solo debemos llenar enero, febrero, marzo.
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 3, 15));

    $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary(2026);

    $tmp = tempnam(sys_get_temp_dir(), 'cons_').'.xlsx';
    file_put_contents($tmp, $binary);
    $sp = IOFactory::load($tmp);
    unlink($tmp);

    // Meta de tickets se escribe en meses procesados (enero-marzo)
    // pero NO en abril-diciembre.
    expect($sp->getSheet(0)->getCell('D14')->getValue())->toBe(0.95); // enero
    expect($sp->getSheet(0)->getCell('D16')->getValue())->toBe(0.95); // marzo

    CarbonImmutable::setTestNow();
});
