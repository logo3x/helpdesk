<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Mantenimientos Programados</title>
    <style>
        @page { margin: 20mm 15mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #18181b; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0 0 4px 0; color: #18181b; }
        h2 { font-size: 13px; margin: 20px 0 8px 0; padding-bottom: 3px; border-bottom: 1px solid #d4d4d8; color: #3f3f46; }
        .header-meta { color: #71717a; font-size: 10px; margin-bottom: 12px; }
        .kpi-row { width: 100%; margin: 12px 0; border-collapse: collapse; }
        .kpi-row td { width: 25%; padding: 10px; border: 1px solid #d4d4d8; vertical-align: top; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #71717a; letter-spacing: 0.5px; }
        .kpi-value { font-size: 22px; font-weight: bold; margin: 4px 0; }
        .kpi-hint { font-size: 9px; color: #a1a1aa; }
        .kpi-success .kpi-value { color: #16a34a; }
        .kpi-danger  .kpi-value { color: #dc2626; }
        .kpi-warning .kpi-value { color: #d97706; }
        .kpi-info    .kpi-value { color: #2563eb; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data th, table.data td { border: 1px solid #d4d4d8; padding: 5px 7px; text-align: left; }
        table.data th { background: #f4f4f5; font-size: 9px; text-transform: uppercase; }
        table.data td { font-size: 10px; }
        .compliance-good { color: #16a34a; font-weight: bold; }
        .compliance-warn { color: #d97706; font-weight: bold; }
        .compliance-bad  { color: #dc2626; font-weight: bold; }
        .reason-row { padding: 3px 0; border-bottom: 1px dotted #e4e4e7; font-size: 10px; }
        .reason-count { display: inline-block; width: 30px; text-align: right; font-weight: bold; color: #dc2626; }
        .empty { color: #a1a1aa; font-style: italic; font-size: 10px; padding: 6px 0; }
        .footer { position: fixed; bottom: 5mm; left: 15mm; right: 15mm; text-align: center; font-size: 8px; color: #a1a1aa; border-top: 1px solid #e4e4e7; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>Informe de Mantenimientos Programados</h1>
    <div class="header-meta">
        Ventana: últimos {{ $window }} días ·
        Desde {{ $from->translatedFormat('d M Y') }} hasta {{ $to->translatedFormat('d M Y') }} ·
        Generado el {{ $generated_at->translatedFormat('d M Y H:i') }}
    </div>

    <h2>Indicadores clave</h2>
    <table class="kpi-row">
        <tr>
            <td class="kpi-info">
                <div class="kpi-label">Total programados</div>
                <div class="kpi-value">{{ $kpi['total'] }}</div>
                <div class="kpi-hint">En la ventana seleccionada</div>
            </td>
            <td class="kpi-success">
                <div class="kpi-label">Cumplidos</div>
                <div class="kpi-value">{{ $kpi['cumplidos'] }}</div>
                <div class="kpi-hint">{{ $kpi['compliance_pct'] !== null ? $kpi['compliance_pct'].'% de cumplimiento' : 'Sin datos' }}</div>
            </td>
            <td class="kpi-danger">
                <div class="kpi-label">No cumplidos</div>
                <div class="kpi-value">{{ $kpi['no_cumplidos'] }}</div>
                <div class="kpi-hint">Reportados con motivo</div>
            </td>
            <td class="kpi-warning">
                <div class="kpi-label">Vencidos sin cerrar</div>
                <div class="kpi-value">{{ $kpi['vencidos'] }}</div>
                <div class="kpi-hint">Pendientes con fecha pasada</div>
            </td>
        </tr>
    </table>

    <h2>Distribución mensual</h2>
    @if(empty($monthly))
        <p class="empty">No hay datos en esta ventana.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th style="text-align:right;">Cumplidos</th>
                    <th style="text-align:right;">No cumplidos</th>
                    <th style="text-align:right;">Pendientes</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthly as $month => $m)
                    <tr>
                        <td>{{ $month }}</td>
                        <td style="text-align:right; color:#16a34a;">{{ $m['cumplidos'] }}</td>
                        <td style="text-align:right; color:#dc2626;">{{ $m['no_cumplidos'] }}</td>
                        <td style="text-align:right; color:#71717a;">{{ $m['pendientes'] }}</td>
                        <td style="text-align:right; font-weight:bold;">{{ $m['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Top razones de no cumplimiento</h2>
    @if(empty($notCompletedReasons))
        <p class="empty">Sin registros de "no cumplido" en esta ventana.</p>
    @else
        @foreach($notCompletedReasons as $r)
            <div class="reason-row">
                <span class="reason-count">{{ $r['count'] }}</span>
                {{ $r['reason'] }}
            </div>
        @endforeach
    @endif

    <h2>Ranking por agente</h2>
    @if(empty($byAgent))
        <p class="empty">Aún no hay mantenimientos asignados.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Agente</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:right;">Cumplidos</th>
                    <th style="text-align:right;">No cumplidos</th>
                    <th style="text-align:right;">Pendientes</th>
                    <th style="text-align:right;">% Cumpl.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byAgent as $row)
                    <tr>
                        <td>{{ $row['agent_name'] }}</td>
                        <td style="text-align:right;">{{ $row['total'] }}</td>
                        <td style="text-align:right; color:#16a34a;">{{ $row['cumplidos'] }}</td>
                        <td style="text-align:right; color:#dc2626;">{{ $row['no_cumplidos'] }}</td>
                        <td style="text-align:right; color:#71717a;">{{ $row['pendientes'] }}</td>
                        <td style="text-align:right;">
                            @php($c = $row['compliance_pct'])
                            <span class="{{ $c >= 90 ? 'compliance-good' : ($c >= 70 ? 'compliance-warn' : 'compliance-bad') }}">
                                {{ $c }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Mantenimientos no cumplidos — detalle</h2>
    @if(empty($notCompletedList))
        <p class="empty">No hay mantenimientos marcados como "no cumplido" en esta ventana.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Activo</th>
                    <th>Tipo</th>
                    <th>Agente</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($notCompletedList as $row)
                    <tr>
                        <td>#{{ $row['id'] }}</td>
                        <td>{{ $row['scheduled_at'] }}</td>
                        <td>{{ $row['asset_tag'] }}</td>
                        <td>{{ $row['asset_type'] }}</td>
                        <td>{{ $row['agent_name'] }}</td>
                        <td>{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Helpdesk Confipetrol · Informe generado desde el módulo Mantenimientos Programados
    </div>
</body>
</html>
