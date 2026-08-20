<x-filament-panels::page>

    {{-- Datos: nos apoyamos en $this->getViewData() para tener un
         solo lugar de cálculo (mismo bundle que consume el PDF). --}}
    @php($data = $this->getViewData())
    @php($kpi = $data['kpi'])
    @php($monthly = $data['monthly'])
    @php($reasons = $data['notCompletedReasons'])
    @php($byAgent = $data['byAgent'])
    @php($notCompleted = $data['notCompletedList'])

    <style>
        .mr-grid { display: grid; gap: 1rem; grid-template-columns: repeat(2, 1fr); }
        @media (min-width: 900px) { .mr-grid { grid-template-columns: repeat(4, 1fr); } }

        .mr-card {
            background: white;
            border: 1px solid rgb(228 228 231);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .dark .mr-card { background: rgb(24 24 27); border-color: rgb(63 63 70); }

        .mr-kpi-value { font-size: 2rem; font-weight: 700; line-height: 1; margin: 0.25rem 0; }
        .mr-kpi-label { font-size: 0.75rem; text-transform: uppercase; color: rgb(113 113 122); letter-spacing: 0.05em; }
        .mr-kpi-hint { font-size: 0.75rem; color: rgb(113 113 122); margin-top: 0.5rem; }

        .mr-kpi-success .mr-kpi-value { color: rgb(22 163 74); }
        .mr-kpi-danger  .mr-kpi-value { color: rgb(220 38 38); }
        .mr-kpi-warning .mr-kpi-value { color: rgb(217 119 6); }
        .mr-kpi-info    .mr-kpi-value { color: rgb(37 99 235); }

        .mr-section-title { font-size: 1rem; font-weight: 600; margin: 0 0 0.75rem 0; }

        .mr-charts { display: grid; gap: 1rem; grid-template-columns: 1fr; margin-top: 1.5rem; }
        @media (min-width: 1000px) { .mr-charts { grid-template-columns: 1.4fr 1fr; } }

        .mr-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .mr-table th, .mr-table td { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 1px solid rgb(228 228 231); }
        .dark .mr-table th, .dark .mr-table td { border-color: rgb(63 63 70); }
        .mr-table th { background: rgb(244 244 245); font-weight: 600; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; }
        .dark .mr-table th { background: rgb(39 39 42); }
        .mr-table tr:hover td { background: rgb(250 250 250); }
        .dark .mr-table tr:hover td { background: rgb(39 39 42); }

        .mr-bar { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; }
        .mr-bar-label { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem; }
        .mr-bar-track { flex: 2 1 auto; background: rgb(228 228 231); height: 8px; border-radius: 4px; overflow: hidden; }
        .dark .mr-bar-track { background: rgb(63 63 70); }
        .mr-bar-fill { height: 100%; background: rgb(220 38 38); border-radius: 4px; }
        .mr-bar-count { flex: 0 0 auto; font-size: 0.85rem; font-variant-numeric: tabular-nums; color: rgb(113 113 122); }

        .mr-status-badge {
            display: inline-block; padding: 0.125rem 0.5rem;
            border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
        }
        .mr-status-cumplido    { background: rgb(220 252 231); color: rgb(22 101 52); }
        .mr-status-nocumplido  { background: rgb(254 226 226); color: rgb(153 27 27); }
        .mr-status-pendiente   { background: rgb(228 228 231); color: rgb(63 63 70); }
        .dark .mr-status-cumplido   { background: rgba(22, 163, 74, 0.15); color: rgb(134 239 172); }
        .dark .mr-status-nocumplido { background: rgba(220, 38, 38, 0.15); color: rgb(252 165 165); }
        .dark .mr-status-pendiente  { background: rgb(63 63 70); color: rgb(212 212 216); }
    </style>

    {{-- Filtro de ventana --}}
    <div class="flex items-center gap-3 mb-4">
        <label class="text-sm font-medium">Ventana:</label>
        <select wire:model.live="window" class="fi-select-input rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-sm">
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 90 días</option>
            <option value="180">Últimos 180 días</option>
            <option value="365">Último año</option>
        </select>
        <span class="text-xs text-gray-500 ml-auto">
            Desde {{ $data['from']->translatedFormat('d M Y') }} hasta {{ $data['to']->translatedFormat('d M Y') }}
        </span>
    </div>

    {{-- KPIs --}}
    <div class="mr-grid">
        <div class="mr-card mr-kpi-info">
            <div class="mr-kpi-label">Total programados</div>
            <div class="mr-kpi-value">{{ $kpi['total'] }}</div>
            <div class="mr-kpi-hint">En la ventana seleccionada</div>
        </div>
        <div class="mr-card mr-kpi-success">
            <div class="mr-kpi-label">Cumplidos</div>
            <div class="mr-kpi-value">{{ $kpi['cumplidos'] }}</div>
            <div class="mr-kpi-hint">{{ $kpi['compliance_pct'] !== null ? $kpi['compliance_pct'].'% de cumplimiento' : 'Sin datos' }}</div>
        </div>
        <div class="mr-card mr-kpi-danger">
            <div class="mr-kpi-label">No cumplidos</div>
            <div class="mr-kpi-value">{{ $kpi['no_cumplidos'] }}</div>
            <div class="mr-kpi-hint">Reportados con motivo</div>
        </div>
        <div class="mr-card mr-kpi-warning">
            <div class="mr-kpi-label">Vencidos sin cerrar</div>
            <div class="mr-kpi-value">{{ $kpi['vencidos'] }}</div>
            <div class="mr-kpi-hint">Pendientes con fecha pasada</div>
        </div>
    </div>

    {{-- Charts row: mensual + top razones --}}
    <div class="mr-charts">
        {{-- Cumplimiento mensual --}}
        <div class="mr-card">
            <h3 class="mr-section-title">Cumplimiento mensual</h3>
            @if(empty($monthly))
                <p class="text-sm text-gray-500">No hay datos en esta ventana.</p>
            @else
                <canvas id="mr-monthly" height="260"></canvas>
            @endif
        </div>

        {{-- Razones de no cumplimiento --}}
        <div class="mr-card">
            <h3 class="mr-section-title">Top razones de no cumplimiento</h3>
            @if(empty($reasons))
                <p class="text-sm text-gray-500">Sin registros de "no cumplido" en esta ventana.</p>
            @else
                @php($maxCount = collect($reasons)->max('count') ?: 1)
                @foreach($reasons as $r)
                    <div class="mr-bar">
                        <span class="mr-bar-label" title="{{ $r['reason'] }}">{{ \Illuminate\Support\Str::limit($r['reason'], 40) }}</span>
                        <span class="mr-bar-track">
                            <span class="mr-bar-fill" style="width: {{ round(($r['count'] / $maxCount) * 100) }}%;"></span>
                        </span>
                        <span class="mr-bar-count">{{ $r['count'] }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Ranking por agente --}}
    <div class="mr-card" style="margin-top: 1.5rem;">
        <h3 class="mr-section-title">Ranking por agente</h3>
        @if(empty($byAgent))
            <p class="text-sm text-gray-500">Aún no hay mantenimientos asignados en esta ventana.</p>
        @else
            <table class="mr-table">
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
                            <td style="text-align:right;">{{ $row['cumplidos'] }}</td>
                            <td style="text-align:right;">{{ $row['no_cumplidos'] }}</td>
                            <td style="text-align:right;">{{ $row['pendientes'] }}</td>
                            <td style="text-align:right; font-weight:600;">
                                @php($c = $row['compliance_pct'])
                                <span style="color: {{ $c >= 90 ? 'rgb(22 163 74)' : ($c >= 70 ? 'rgb(217 119 6)' : 'rgb(220 38 38)') }};">
                                    {{ $c }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Drill-down: no cumplidos --}}
    <div class="mr-card" style="margin-top: 1.5rem;">
        <h3 class="mr-section-title">Mantenimientos no cumplidos — detalle</h3>
        @if(empty($notCompleted))
            <p class="text-sm text-gray-500">✅ No hay mantenimientos marcados como "no cumplido" en esta ventana.</p>
        @else
            <table class="mr-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Activo</th>
                        <th>Tipo</th>
                        <th>Agente</th>
                        <th>Motivo</th>
                        <th>Cerrado el</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($notCompleted as $row)
                        <tr>
                            <td>{{ $row['scheduled_at'] }}</td>
                            <td>#{{ $row['id'] }} · {{ $row['asset_tag'] }}</td>
                            <td><span class="mr-status-badge mr-status-pendiente">{{ $row['asset_type'] }}</span></td>
                            <td>{{ $row['agent_name'] }}</td>
                            <td>{{ $row['reason'] }}</td>
                            <td>{{ $row['closed_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Chart.js CDN (mismo patrón que ChatbotMetrics) --}}
    @if(!empty($monthly))
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            (function() {
                const labels = @json(array_keys($monthly));
                const cumplidos = @json(collect($monthly)->pluck('cumplidos')->all());
                const noCumplidos = @json(collect($monthly)->pluck('no_cumplidos')->all());
                const pendientes = @json(collect($monthly)->pluck('pendientes')->all());

                const el = document.getElementById('mr-monthly');
                if (!el) return;
                if (window.mrMonthlyChart) { window.mrMonthlyChart.destroy(); }

                window.mrMonthlyChart = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Cumplidos',    data: cumplidos,    backgroundColor: 'rgb(22 163 74)' },
                            { label: 'No cumplidos', data: noCumplidos,  backgroundColor: 'rgb(220 38 38)' },
                            { label: 'Pendientes',   data: pendientes,   backgroundColor: 'rgb(161 161 170)' },
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true },
                            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
                        },
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            })();
        </script>
    @endif

</x-filament-panels::page>
