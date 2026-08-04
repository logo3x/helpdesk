<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de Aceptación — {{ $asset->hostname ?? $asset->asset_tag ?? "Activo #{$asset->id}" }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 15mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.4; color: #000; margin: 0; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 16px; }
        .header h1 { font-size: 13pt; margin: 0 0 2px; }
        .header p { font-size: 8pt; margin: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td, th { border: 1px solid #000; padding: 4px 8px; font-size: 8.5pt; }
        th { background: #f0f0f0; font-weight: bold; text-align: left; width: 35%; }
        .footer { margin-top: 30px; border-top: 1px solid #000; padding-top: 12px; font-size: 8pt; color: #333; }
        .accepted-badge { background: #d1fae5; border: 1px solid #059669; color: #065f46; padding: 4px 12px; font-weight: bold; display: inline-block; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Constancia de Aceptación de Activo</h1>
        <p>Confipetrol S.A. — Tecnología de la Información</p>
    </div>

    <div class="accepted-badge">✓ ACEPTADO DIGITALMENTE EL {{ \Carbon\Carbon::parse($acceptedAt)->format('d/m/Y H:i') }}</div>

    <table>
        <tr><th>Activo</th><td>{{ $asset->hostname ?? '—' }}</td></tr>
        <tr><th>TAG / Etiqueta</th><td>{{ $asset->asset_tag ?? '—' }}</td></tr>
        <tr><th>Serial</th><td>{{ $asset->serial_number ?? '—' }}</td></tr>
        <tr><th>Tipo</th><td>{{ ucfirst($asset->type ?? '—') }}</td></tr>
        <tr><th>Fabricante / Modelo</th><td>{{ $asset->manufacturer }} {{ $asset->model }}</td></tr>
        <tr><th>Custodio</th><td>{{ $asset->user?->name ?? '—' }}</td></tr>
        <tr><th>Departamento</th><td>{{ $asset->department?->name ?? '—' }}</td></tr>
        <tr><th>Fecha de aceptación</th><td>{{ \Carbon\Carbon::parse($acceptedAt)->format('d/m/Y H:i') }}</td></tr>
    </table>

    <p>El custodio indicado confirmó digitalmente la recepción del equipo a través del Portal de Helpdesk Confipetrol.</p>

    <div class="footer">
        Documento generado automáticamente — {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
