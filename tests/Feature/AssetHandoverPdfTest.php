<?php

use App\Models\Asset;
use App\Models\AssetHandover;
use App\Models\Department;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

function makeHandover(array $overrides = []): AssetHandover
{
    $dept = Department::firstOrCreate(['slug' => 'ti'], ['name' => 'TI']);
    $delivered = User::factory()->create(['identification' => '11111', 'department_id' => $dept->id]);
    $received = User::factory()->create(['identification' => '99999', 'position' => 'Operador', 'department_id' => $dept->id]);
    $asset = Asset::create(['asset_tag' => 'TST-001', 'status' => 'active', 'type' => 'laptop']);

    return AssetHandover::create(array_merge([
        'acta_number' => 9999,
        'asset_id' => $asset->id,
        'delivered_by_user_id' => $delivered->id,
        'received_by_user_id' => $received->id,
        'delivered_at' => now(),
        'asset_tag_snapshot' => 'TST-001',
        'asset_type_snapshot' => 'laptop',
        'manufacturer_snapshot' => 'Dell',
        'model_snapshot' => 'Latitude 7420',
        'serial_snapshot' => 'ABC123',
        'sap_code_snapshot' => 'SAP-X',
        'field_snapshot' => 'PORE',
        'condition_at_delivery' => 'bueno',
        'reference' => 'Entrega de LAPTOP',
        'observations' => 'Acta # 9999 - CON CARGADOR',
        'template_version' => 'IT-ADM1-F-5_v3',
    ], $overrides));
}

it('el PDF de acta cabe en una sola hoja letter', function () {
    $handover = makeHandover();
    $handover->load(['receivedBy', 'deliveredBy', 'project']);

    $pdf = Pdf::loadView('pdfs.asset-handover', ['handover' => $handover])
        ->setPaper('letter', 'portrait');

    $pdf->render();

    expect($pdf->getDomPDF()->get_canvas()->get_page_count())->toBe(1);
});

it('sigue cabiendo en una hoja con observaciones extensas', function () {
    $handover = makeHandover([
        'observations' => str_repeat('Observación de prueba con texto largo que ocupa espacio. ', 8),
    ]);
    $handover->load(['receivedBy', 'deliveredBy', 'project']);

    $pdf = Pdf::loadView('pdfs.asset-handover', ['handover' => $handover])
        ->setPaper('letter', 'portrait');

    $pdf->render();

    expect($pdf->getDomPDF()->get_canvas()->get_page_count())->toBe(1);
});
