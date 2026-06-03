<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de cumplimiento SLA — {{ now()->translatedFormat('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #1a1a1a;
            margin: 0;
        }
        h1 { font-size: 14pt; margin: 0 0 4px 0; }
        h2 { font-size: 11pt; margin: 14px 0 6px 0; border-bottom: 1px solid #d4d4d4; padding-bottom: 3px; }
        .header { display: table; width: 100%; margin-bottom: 12px; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8pt; color: #555; }
        .header-right .logo { max-height: 36px; }
        .meta { color: #666; font-size: 8.5pt; }

        .kpi-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .kpi { display: table-cell; width: 33%; border: 1px solid #d4d4d4; padding: 8px 10px; vertical-align: top; }
        .kpi-label { font-size: 8pt; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .kpi-value { font-size: 16pt; font-weight: bold; margin-top: 2px; }
        .kpi-sub { font-size: 8pt; color: #666; }

        table.matrix { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.matrix th, table.matrix td { border: 1px solid #d4d4d4; padding: 4px 6px; font-size: 8.5pt; text-align: center; }
        table.matrix th { background: #f3f4f6; font-weight: bold; }
        table.matrix td.dept { text-align: left; font-weight: bold; background: #fafafa; }
        .pct-good { color: #15803d; font-weight: bold; }
        .pct-warn { color: #b45309; font-weight: bold; }
        .pct-bad { color: #b91c1c; font-weight: bold; }
        .muted { color: #999; }

        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border-bottom: 1px solid #e5e5e5; padding: 4px 6px; font-size: 8.5pt; text-align: left; vertical-align: top; }
        table.list th { background: #f3f4f6; font-weight: bold; }
        .risk-breached { color: #b91c1c; font-weight: bold; }
        .risk-warning { color: #b45309; }

        .footer { margin-top: 14px; border-top: 1px solid #d4d4d4; padding-top: 6px; font-size: 7.5pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1>Reporte de cumplimiento SLA</h1>
            <div class="meta">Ventana: últimos {{ $window }} días · Generado: {{ now()->translatedFormat('d/m/Y H:i') }}</div>
        </div>
        <div class="header-right">
            @if (file_exists(public_path('images/logo-confipetrol-dark.png')))
                <img src="{{ public_path('images/logo-confipetrol-dark.png') }}" class="logo" alt="Confipetrol">
            @else
                <strong>CONFIPETROL</strong>
            @endif
            <div>Helpdesk · IT</div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">Tickets resueltos</div>
            <div class="kpi-value">{{ $summary['resolved'] }}</div>
            <div class="kpi-sub">Con SLA aplicado en la ventana</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">SLA incumplido</div>
            <div class="kpi-value" style="color: #b91c1c;">{{ $summary['breached'] }}</div>
            <div class="kpi-sub">{{ $summary['resolved'] > 0 ? round($summary['breached'] / $summary['resolved'] * 100, 1).'%' : '—' }} del total</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Cumplimiento</div>
            <div class="kpi-value" style="color: {{ ($summary['compliance'] ?? 0) >= 90 ? '#15803d' : (($summary['compliance'] ?? 0) >= 70 ? '#b45309' : '#b91c1c') }};">
                {{ $summary['compliance'] !== null ? $summary['compliance'].'%' : '—' }}
            </div>
            <div class="kpi-sub">Objetivo: ≥ 90%</div>
        </div>
    </div>

    <h2>Cumplimiento por departamento y prioridad</h2>
    @if (empty($report))
        <p class="muted">No hay datos para la ventana seleccionada.</p>
    @else
        <table class="matrix">
            <thead>
                <tr>
                    <th rowspan="2" style="vertical-align: middle;">Departamento</th>
                    @foreach ($priorities as $priority)
                        <th colspan="2">{{ $priority->getLabel() }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($priorities as $priority)
                        <th>Total</th>
                        <th>%</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report as $row)
                    <tr>
                        <td class="dept">{{ $row['department'] }}</td>
                        @foreach ($row['priorities'] as $cell)
                            <td>{{ $cell['total'] }}</td>
                            <td>
                                @if ($cell['compliance'] === null)
                                    <span class="muted">—</span>
                                @else
                                    @php($pct = $cell['compliance'])
                                    <span class="{{ $pct >= 90 ? 'pct-good' : ($pct >= 70 ? 'pct-warn' : 'pct-bad') }}">{{ $pct }}%</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Tickets en riesgo</h2>
    @if ($atRisk->isEmpty())
        <p class="muted">Sin tickets en riesgo de incumplir SLA en las próximas 24 horas.</p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th style="width: 12%;">Ticket</th>
                    <th>Asunto</th>
                    <th style="width: 18%;">Departamento</th>
                    <th style="width: 12%;">Asignado a</th>
                    <th style="width: 14%; text-align: right;">Vence</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($atRisk as $item)
                    <tr>
                        <td><strong>{{ $item['ticket']->number }}</strong></td>
                        <td>{{ \Illuminate\Support\Str::limit($item['ticket']->subject, 70) }}</td>
                        <td>{{ $item['ticket']->department?->name ?? '—' }}</td>
                        <td>{{ $item['ticket']->assignee?->name ?? 'Sin asignar' }}</td>
                        <td style="text-align: right;">
                            @if ($item['is_breached'])
                                <span class="risk-breached">Vencido hace {{ abs($item['hours_left']) }} h</span>
                            @else
                                <span class="risk-warning">En {{ $item['hours_left'] }} h</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Escalaciones recientes</h2>
    @if ($escalations->isEmpty())
        <p class="muted">No se registraron escalaciones SLA.</p>
    @else
        <table class="list">
            <thead>
                <tr>
                    <th style="width: 12%;">Ticket</th>
                    <th style="width: 14%;">Tipo</th>
                    <th style="width: 16%;">Notificado a</th>
                    <th>Asunto del ticket</th>
                    <th style="width: 14%; text-align: right;">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($escalations as $log)
                    <tr>
                        <td><strong>{{ $log->ticket?->number ?? '—' }}</strong></td>
                        <td>{{ ucfirst((string) $log->type) }}</td>
                        <td>{{ $log->notifiedUser?->name ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($log->ticket?->subject ?? '', 60) }}</td>
                        <td style="text-align: right;">{{ $log->created_at?->translatedFormat('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Confipetrol · Helpdesk IT · Reporte generado automáticamente por el sistema. Documento confidencial.
    </div>
</body>
</html>
