<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de vida — {{ $record->hostname ?: ('Activo #'.$record->id) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: #fff; }

        .page { max-width: 800px; margin: 0 auto; padding: 32px 36px; }

        /* Header */
        .header { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: #fff; border-radius: 12px; padding: 24px 28px; margin-bottom: 24px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .header-logo { font-size: 11px; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 12px; }
        .header h1 { font-size: 22px; font-weight: 700; color: #fff; }
        .header-sub { font-size: 13px; color: #94a3b8; margin-top: 4px; }
        .header-meta { text-align: right; font-size: 11px; color: #94a3b8; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; margin-top: 8px; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f1f5f9; color: #475569; }
        .badge-retired  { background: #fee2e2; color: #991b1b; }
        .badge-repair   { background: #fef3c7; color: #92400e; }

        /* Tarjetas resumen */
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; background: #f8fafc; }
        .card-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; margin-bottom: 4px; }
        .card-value { font-size: 13px; font-weight: 600; color: #0f172a; line-height: 1.3; }
        .card-sub { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Specs técnicas */
        .specs { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 24px; overflow: hidden; }
        .specs-header { background: #f1f5f9; padding: 10px 16px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .specs-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; }
        .spec-item { padding: 10px 16px; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .spec-item:nth-child(3n) { border-right: none; }
        .spec-label { font-size: 10px; color: #94a3b8; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; }
        .spec-value { font-size: 12px; color: #1e293b; font-weight: 500; }

        /* Timeline */
        .timeline-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .timeline-title { font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
        .timeline-count { font-size: 11px; color: #94a3b8; }
        .timeline { position: relative; padding-left: 24px; }
        .timeline::before { content: ''; position: absolute; left: 7px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .event { position: relative; margin-bottom: 14px; }
        .event:last-child { margin-bottom: 0; }
        .event-dot { position: absolute; left: -21px; top: 10px; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
        .dot-primary { background: #6366f1; box-shadow: 0 0 0 2px #e0e7ff; }
        .dot-warning  { background: #f59e0b; box-shadow: 0 0 0 2px #fef3c7; }
        .dot-info     { background: #0ea5e9; box-shadow: 0 0 0 2px #e0f2fe; }
        .dot-gray     { background: #94a3b8; box-shadow: 0 0 0 2px #f1f5f9; }
        .event-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; background: #f8fafc; }
        .event-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .event-title { font-size: 12px; font-weight: 700; color: #0f172a; }
        .event-date { font-size: 10px; color: #94a3b8; white-space: nowrap; }
        .event-desc { font-size: 11px; color: #475569; margin-top: 4px; }
        .event-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; margin-top: 6px; }
        .meta-item { font-size: 10px; }
        .meta-label { color: #94a3b8; }
        .meta-value { color: #334155; font-weight: 600; }

        /* Footer */
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
            .page { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Botón imprimir (solo en pantalla) --}}
    <div class="no-print" style="text-align:right; margin-bottom:16px;">
        <button onclick="window.print()"
            style="background:#1e293b; color:#fff; border:none; padding:8px 20px; border-radius:8px; font-size:13px; cursor:pointer; font-family:inherit;">
            🖨 Imprimir / Guardar PDF
        </button>
    </div>

    {{-- ── HEADER ─────────────────────────────────────────── --}}
    <div class="header">
        <div class="header-logo">Confipetrol · Inventario IT</div>
        <div class="header-top">
            <div>
                <h1>{{ $record->hostname ?: ('Activo #'.$record->id) }}</h1>
                <div class="header-sub">
                    {{ implode(' ', array_filter([$record->manufacturer, $record->model])) ?: '—' }}
                    @if ($record->serial_number) · S/N {{ $record->serial_number }} @endif
                </div>
                @if ($record->asset_tag && $record->asset_tag !== 'N/A')
                    <div style="font-size:11px; color:#94a3b8; margin-top:6px;">TAG: {{ $record->asset_tag }}</div>
                @endif
            </div>
            <div class="header-meta">
                <div>Generado: {{ now()->translatedFormat('d M Y · H:i') }}</div>
                @php
                    $badgeClass = ['active'=>'badge-active','inactive'=>'badge-inactive','retired'=>'badge-retired','repair'=>'badge-repair'][$record->status] ?? 'badge-inactive';
                    $badgeLabel = ['active'=>'Activo','inactive'=>'Inactivo','retired'=>'Dado de baja','repair'=>'En reparación'][$record->status] ?? ucfirst((string)$record->status);
                @endphp
                <div class="badge {{ $badgeClass }}">{{ $badgeLabel }}</div>
            </div>
        </div>
    </div>

    {{-- ── TARJETAS ────────────────────────────────────────── --}}
    <div class="cards">
        <div class="card">
            <div class="card-label">Custodio</div>
            <div class="card-value">{{ $record->user?->name ?? '— Sin asignar —' }}</div>
            @if ($record->user?->position)
                <div class="card-sub">{{ $record->user->position }}</div>
            @endif
        </div>
        <div class="card">
            <div class="card-label">Proyecto</div>
            <div class="card-value">{{ $record->project?->code ?? '—' }}</div>
            @if ($record->project?->name)
                <div class="card-sub">{{ $record->project->name }}</div>
            @endif
        </div>
        <div class="card">
            <div class="card-label">Departamento</div>
            <div class="card-value">{{ $record->department?->name ?? '—' }}</div>
        </div>
        <div class="card">
            <div class="card-label">Ubicación / Campo</div>
            <div class="card-value">{{ $record->field ?: ($record->location_zone ?: '—') }}</div>
            @if ($record->management_area)
                <div class="card-sub">{{ $record->management_area }}</div>
            @endif
        </div>
    </div>

    {{-- ── SPECS TÉCNICAS ──────────────────────────────────── --}}
    @if ($record->os_name || $record->cpu_model || $record->ram_mb || $record->ip_address)
        <div class="specs">
            <div class="specs-header">Especificaciones técnicas</div>
            <div class="specs-grid">
                @if ($record->os_name)
                    <div class="spec-item">
                        <div class="spec-label">Sistema operativo</div>
                        <div class="spec-value">{{ $record->os_name }} {{ $record->os_version }}</div>
                    </div>
                @endif
                @if ($record->cpu_model)
                    <div class="spec-item">
                        <div class="spec-label">Procesador</div>
                        <div class="spec-value">{{ $record->cpu_model }} ({{ $record->cpu_cores }} núcleos)</div>
                    </div>
                @endif
                @if ($record->ram_mb)
                    <div class="spec-item">
                        <div class="spec-label">Memoria RAM</div>
                        <div class="spec-value">{{ round($record->ram_mb / 1024, 1) }} GB</div>
                    </div>
                @endif
                @if ($record->disk_total_gb)
                    <div class="spec-item">
                        <div class="spec-label">Almacenamiento</div>
                        <div class="spec-value">{{ $record->disk_total_gb }} GB</div>
                    </div>
                @endif
                @if ($record->ip_address)
                    <div class="spec-item">
                        <div class="spec-label">IP</div>
                        <div class="spec-value">{{ $record->ip_address }}</div>
                    </div>
                @endif
                @if ($record->mac_address)
                    <div class="spec-item">
                        <div class="spec-label">MAC</div>
                        <div class="spec-value">{{ $record->mac_address }}</div>
                    </div>
                @endif
                @if ($record->purchased_at)
                    <div class="spec-item">
                        <div class="spec-label">Fecha de compra</div>
                        <div class="spec-value">{{ $record->purchased_at->translatedFormat('d M Y') }}</div>
                    </div>
                @endif
                @if ($record->warranty_expires_at)
                    <div class="spec-item">
                        <div class="spec-label">Garantía hasta</div>
                        <div class="spec-value">{{ $record->warranty_expires_at->translatedFormat('d M Y') }}</div>
                    </div>
                @endif
                @if ($record->sap_code)
                    <div class="spec-item">
                        <div class="spec-label">Código SAP</div>
                        <div class="spec-value">{{ $record->sap_code }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ── TIMELINE ────────────────────────────────────────── --}}
    <div class="timeline-header">
        <span class="timeline-title">Línea de tiempo</span>
        <span class="timeline-count">{{ count($events) }} evento(s)</span>
    </div>

    @if (empty($events))
        <p style="color:#94a3b8; font-size:12px; text-align:center; padding:20px;">Sin eventos registrados.</p>
    @else
        <div class="timeline">
            @foreach ($events as $event)
                @php
                    $dotClass = ['primary'=>'dot-primary','warning'=>'dot-warning','info'=>'dot-info','gray'=>'dot-gray'][$event['color']] ?? 'dot-gray';
                @endphp
                <div class="event">
                    <div class="event-dot {{ $dotClass }}"></div>
                    <div class="event-card">
                        <div class="event-top">
                            <span class="event-title">{{ $event['title'] }}</span>
                            <span class="event-date">{{ $event['date']->translatedFormat('d M Y · H:i') }}</span>
                        </div>
                        @if ($event['description'])
                            <div class="event-desc">{{ $event['description'] }}</div>
                        @endif
                        @if (!empty($event['meta']))
                            <div class="event-meta">
                                @foreach ($event['meta'] as $label => $value)
                                    @if (!blank($value))
                                        <div class="meta-item">
                                            <span class="meta-label">{{ $label }}: </span>
                                            <span class="meta-value">{{ $value }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── FOOTER ──────────────────────────────────────────── --}}
    <div class="footer">
        <span>Confipetrol Andina S.A. · Gestión IT</span>
        <span>Generado el {{ now()->translatedFormat('d \d\e F \d\e Y \a \l\a\s H:i') }}</span>
    </div>

</div>
</body>
</html>
