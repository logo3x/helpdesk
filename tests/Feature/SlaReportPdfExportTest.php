<?php

use App\Filament\Pages\SlaReport as AdminSlaReport;
use App\Filament\Soporte\Pages\SlaReport as SoporteSlaReport;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('el SlaReport del panel admin expone exportPdf() que devuelve un StreamedResponse', function () {
    $page = new AdminSlaReport;
    $page->window = '30';

    $response = $page->exportPdf();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('el SlaReport del panel soporte expone exportPdf() también', function () {
    $page = new SoporteSlaReport;
    $page->window = '30';

    $response = $page->exportPdf();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('el filename incluye la fecha actual', function () {
    $page = new AdminSlaReport;
    $page->window = '30';

    $response = $page->exportPdf();

    $disposition = $response->headers->get('Content-Disposition');
    expect($disposition)->toContain('reporte-sla-'.now()->format('Y-m-d').'.pdf');
});
