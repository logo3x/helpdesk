<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Encuestas de satisfacción — {{ now()->translatedFormat('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; line-height: 1.3; color: #1a1a1a; margin: 0; }
        h1 { font-size: 14pt; margin: 0 0 4px 0; }
        h2 { font-size: 10pt; margin: 10px 0 4px 0; border-bottom: 1px solid #d4d4d4; padding-bottom: 2px; }
        .header { display: table; width: 100%; margin-bottom: 10px; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8pt; color: #555; }
        .header-right img { max-height: 36px; }
        .meta { color: #666; font-size: 8.5pt; }
        .filters { background: #f8f8f8; border: 1px solid #e4e4e7; padding: 6px 10px; border-radius: 4px; margin-bottom: 10px; font-size: 8.5pt; }
        .filter-tag { display: inline-block; margin-right: 10px; }
        .filter-tag b { font-weight: 600; }

        .kpi-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .kpi { display: table-cell; width: 20%; border: 1px solid #d4d4d4; padding: 6px 8px; vertical-align: top; }
        .kpi-label { font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.3px; }
        .kpi-value { font-size: 14pt; font-weight: bold; margin-top: 2px; }

        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border-bottom: 1px solid #e5e5e5; padding: 3px 5px; font-size: 8pt; text-align: left; vertical-align: top; }
        table.list th { background: #f3f4f6; font-weight: bold; }
        .badge-ticket { background: #e0f2fe; color: #075985; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 7.5pt; }
        .badge-mto { background: #fef3c7; color: #854d0e; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 7.5pt; }
        .footer { margin-top: 10px; border-top: 1px solid #d4d4d4; padding-top: 4px; font-size: 7pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>Encuestas de satisfacción</h1>
            <div class="meta">Generado: {{ $generatedAt->translatedFormat('d/m/Y H:i') }} · Confipetrol · Helpdesk IT</div>
        </div>
        <div class="header-right">
            @if (file_exists(public_path('images/logo-confipetrol-dark.png')))
                <img src="{{ public_path('images/logo-confipetrol-dark.png') }}" alt="Confipetrol">
            @else
                <strong>CONFIPETROL</strong>
            @endif
        </div>
    </div>

    <div class="filters">
        <span class="filter-tag"><b>Origen:</b> {{ $sourceLabel }}</span>
        <span class="filter-tag"><b>Departamento:</b> {{ $departmentLabel }}</span>
        <span class="filter-tag"><b>Ventana:</b> {{ $windowLabel }}</span>
        <span class="filter-tag"><b>Calificación mínima:</b> {{ $minRatingLabel }}</span>
    </div>

    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">Total</div><div class="kpi-value">{{ $stats['total'] }}</div></div>
        <div class="kpi"><div class="kpi-label">Respondidas</div><div class="kpi-value" style="color:#15803d">{{ $stats['answered'] }}</div></div>
        <div class="kpi"><div class="kpi-label">Pendientes</div><div class="kpi-value" style="color:#b45309">{{ $stats['pending'] }}</div></div>
        <div class="kpi"><div class="kpi-label">Promedio</div><div class="kpi-value">{{ $stats['avg'] !== null ? $stats['avg'].'★' : '—' }}</div></div>
        <div class="kpi"><div class="kpi-label">Por origen</div><div class="kpi-value" style="font-size:10pt">{{ $stats['byTicket'] }}T · {{ $stats['byMaintenance'] }}M</div></div>
    </div>

    <h2>Encuestas ({{ $rows->count() }} registros)</h2>

    @if ($rows->isEmpty())
        <p>Sin encuestas con los filtros aplicados.</p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th style="width: 8%;">Origen</th>
                    <th style="width: 20%;">Relacionado con</th>
                    <th style="width: 13%;">Departamento</th>
                    <th style="width: 15%;">Usuario</th>
                    <th style="width: 7%;">Calif.</th>
                    <th>Comentario</th>
                    <th style="width: 11%;">Respondida</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            @if ($row['source'] === 'ticket')
                                <span class="badge-ticket">Ticket</span>
                            @else
                                <span class="badge-mto">Mto</span>
                            @endif
                        </td>
                        <td>{{ $row['related_label'] }}</td>
                        <td>{{ $row['department'] ?? '—' }}</td>
                        <td>{{ $row['user_name'] ?? '—' }}</td>
                        <td>
                            @if ($row['rating'])
                                {{ str_repeat('★', $row['rating']).str_repeat('☆', 5 - $row['rating']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $row['comment'] ? \Illuminate\Support\Str::limit((string) $row['comment'], 180) : '—' }}</td>
                        <td>{{ $row['responded_at']?->translatedFormat('d/m/Y H:i') ?? 'Pendiente' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Confipetrol · Helpdesk IT · Documento confidencial generado automáticamente por el sistema.
    </div>
</body>
</html>
