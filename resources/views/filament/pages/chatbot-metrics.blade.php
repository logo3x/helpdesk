<x-filament-panels::page>

    <style>
        @keyframes cmFadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .cm-kpi  { animation: cmFadeUp .3s ease both; opacity: 0; }
        .cm-section { animation: cmFadeUp .35s ease .1s both; }

        .cm-kpi-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, 1fr);
        }
        @media (min-width: 900px) {
            .cm-kpi-grid { grid-template-columns: repeat(4, 1fr); }
        }

        .cm-chart-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .cm-chart-main { flex: 1 1 520px; min-width: 0; }
        .cm-chart-side { flex: 0 0 280px; min-width: 0; }

        .cm-icon { display:inline-block; width:1rem; height:1rem; vertical-align:middle; flex-shrink:0; }
        .cm-icon-sm { display:inline-block; width:0.75rem; height:0.75rem; vertical-align:middle; flex-shrink:0; }

        .cm-table { width:100%; border-collapse:collapse; font-size:0.8125rem; }
        .cm-table th { background:#f9fafb; padding:0.625rem 0.75rem; font-size:0.6875rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; border-bottom:2px solid #e5e7eb; }
        .cm-table th.right { text-align:right; }
        .cm-table th.center { text-align:center; }
        .cm-table td { padding:0.625rem 0.75rem; border-bottom:1px solid #f3f4f6; color:#374151; vertical-align:middle; }
        .cm-table td.right { text-align:right; }
        .cm-table td.center { text-align:center; }
        .cm-table tr:last-child td { border-bottom:none; }
        .cm-table tr:hover td { background:#f9fafb; }
        .cm-badge { display:inline-block; border-radius:9999px; padding:0.2rem 0.6rem; font-size:0.6875rem; font-weight:600; white-space:nowrap; }
        .cm-neg-item { border-radius:0.75rem; border:1px solid #e5e7eb; background:#fafafa; overflow:hidden; margin-bottom:0.625rem; }
        .cm-neg-meta { display:flex; align-items:center; justify-content:space-between; padding:0.4rem 1rem; background:#f3f4f6; border-bottom:1px solid #e5e7eb; font-size:0.75rem; color:#9ca3af; }
        .cm-neg-body { padding:0.75rem 1rem; font-size:0.8125rem; color:#374151; line-height:1.6; }
    </style>

    {{-- ── Controles: ventana + filtro depto + export ─────────────── --}}
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem">
        <p style="font-size:0.875rem;color:#71717a">
            Datos del chatbot en los últimos <strong>{{ $window }}</strong> días.
            @if ($selectedDepartmentId)
                · Filtrado por departamento.
            @endif
        </p>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem">
            <select wire:model.live="window"
                style="border-radius:0.5rem;border:1px solid #d4d4d8;background:#fff;padding:0.375rem 0.75rem;font-size:0.875rem">
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </select>

            <select wire:model.live="departmentId"
                style="border-radius:0.5rem;border:1px solid #d4d4d8;background:#fff;padding:0.375rem 0.75rem;font-size:0.875rem">
                <option value="">Todos los departamentos</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>

            <button type="button" wire:click="exportExcel"
                style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:0.5rem;background:#059669;padding:0.375rem 0.75rem;font-size:0.875rem;font-weight:500;color:#fff;border:none;cursor:pointer">
                <svg xmlns="http://www.w3.org/2000/svg" style="width:1rem;height:1rem" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Exportar Excel
            </button>
        </div>
    </div>

    {{-- ── KPI cards con comparativa ───────────────────────────────── --}}
    @php
        $deltaSessions = $this->delta($summary['sessions'], $summaryPrev['sessions']);
        $deltaMessages = $this->delta($summary['assistant_messages'], $summaryPrev['assistant_messages']);
        $deltaCsat     = $this->delta($summary['csat_pct'], $summaryPrev['csat_pct']);
        $deltaAuto     = $this->delta($summary['auto_resolution_pct'], $summaryPrev['auto_resolution_pct']);

        $kpis = [
            ['label'=>'Sesiones',        'value'=>$summary['sessions'],            'suffix'=>'',  'delta'=>$deltaSessions, 'hint'=>'Conversaciones iniciadas', 'icon'=>'chat-bubble-left-right', 'color'=>'sky'],
            ['label'=>'Mensajes del bot','value'=>$summary['assistant_messages'],  'suffix'=>'',  'delta'=>$deltaMessages, 'hint'=>'Respuestas dadas',          'icon'=>'cpu-chip',              'color'=>'indigo'],
            ['label'=>'CSAT',            'value'=>$summary['csat_pct'],            'suffix'=>'%', 'delta'=>$deltaCsat,     'hint'=>$summary['helpful'].' 👍 · '.$summary['not_helpful'].' 👎', 'icon'=>'hand-thumb-up', 'color'=>'amber'],
            ['label'=>'Auto-resolución', 'value'=>$summary['auto_resolution_pct'],'suffix'=>'%', 'delta'=>$deltaAuto,     'hint'=>'Sin escalar a ticket',      'icon'=>'check-circle',          'color'=>'emerald'],
        ];

        $colorMap = [
            'sky'     => ['bg'=>'#e0f2fe', 'icon'=>'#0ea5e9'],
            'indigo'  => ['bg'=>'#e0e7ff', 'icon'=>'#6366f1'],
            'amber'   => ['bg'=>'#fef3c7', 'icon'=>'#f59e0b'],
            'emerald' => ['bg'=>'#d1fae5', 'icon'=>'#10b981'],
        ];

        $iconPaths = [
            'chat-bubble-left-right' => 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.269-2.956-2.884-3.071a48.774 48.774 0 0 0-3.461-.21m-8.5 0a48.774 48.774 0 0 0-3.461.21C3.269 3.68 2 5.016 2 6.637v4.172c0 1.621 1.269 2.956 2.884 3.071a49.03 49.03 0 0 0 2.616.08l.002.001 3 3v-3.091a49.03 49.03 0 0 0 1.498-.093',
            'cpu-chip'              => 'M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z',
            'hand-thumb-up'         => 'M6.633 10.25c.806 0 1.533-.446 2.031-1.08a9.041 9.041 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V2.75a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282m0 0h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H13.48c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23H5.904m10.598-9.75H14.25M5.904 18.5c.083.205.173.405.27.602.197.4-.078.898-.523.898h-.908c-.889 0-1.713-.518-1.972-1.368a12 12 0 0 1-.521-3.507c0-1.553.295-3.036.831-4.398C3.387 9.953 4.167 9.5 5 9.5h1.053c.472 0 .745.556.5.96a8.958 8.958 0 0 0-1.302 4.665c0 1.194.232 2.333.654 3.375Z',
            'check-circle'          => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ];
    @endphp

    <div class="cm-kpi-grid"
         x-data="{}"
         x-init="document.querySelectorAll('.cm-kpi').forEach((el,i)=>{ el.style.animationDelay=(i*60)+'ms'; })">
        @foreach ($kpis as $kpi)
            @php $c = $colorMap[$kpi['color']]; @endphp
            <div class="cm-kpi" style="overflow:hidden;border-radius:0.75rem;border:1px solid #e4e4e7;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.07)">
                <div style="padding:1rem">
                    <div style="margin-bottom:0.75rem;display:flex;align-items:center;justify-content:space-between">
                        <div style="display:flex;width:2.25rem;height:2.25rem;align-items:center;justify-content:center;border-radius:0.5rem;background:{{ $c['bg'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width:1.25rem;height:1.25rem;color:{{ $c['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPaths[$kpi['icon']] }}" />
                            </svg>
                        </div>
                        @if ($kpi['delta'])
                            @php
                                $dir = $kpi['delta']['direction'];
                                $pct = $kpi['delta']['pct'];
                                $badgeStyle = match(true) {
                                    $dir === 'flat' => 'color:#a1a1aa;background:#fafafa',
                                    $dir === 'up'   => 'color:#047857;background:#d1fae5',
                                    default         => 'color:#b91c1c;background:#fee2e2',
                                };
                                $arrow = match($dir) { 'up'=>'↑','down'=>'↓',default=>'→' };
                            @endphp
                            <span style="border-radius:9999px;padding:0.125rem 0.5rem;font-size:0.75rem;font-weight:600;{{ $badgeStyle }}">
                                {{ $arrow }} {{ abs($pct) }}%
                            </span>
                        @endif
                    </div>
                    <div style="font-size:1.5rem;font-weight:700;color:#18181b">
                        @if ($kpi['value'] !== null)
                            {{ $kpi['value'] }}{{ $kpi['suffix'] }}
                        @else
                            <span style="color:#d4d4d8">—</span>
                        @endif
                    </div>
                    <div style="margin-top:0.125rem;font-size:0.875rem;font-weight:500;color:#71717a">{{ $kpi['label'] }}</div>
                    <div style="margin-top:0.25rem;font-size:0.75rem;color:#a1a1aa">{{ $kpi['hint'] }} · vs periodo anterior</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Gráficos ─────────────────────────────────────────────────── --}}
    <div class="cm-section cm-chart-grid">
        <div class="cm-chart-main">
        <x-filament::section>
            <x-slot name="heading">Evolución del CSAT y volumen</x-slot>
            <x-slot name="description">Línea naranja = % CSAT diario · Barras azules = mensajes del bot por día.</x-slot>

            <div class="relative h-64 w-full">
                <canvas id="csat-trend-chart"
                    x-data="{
                        chart: null,
                        init() {
                            this.render(@js($csatTrend));
                            window.addEventListener('chatbot-metrics-updated', e => this.render(e.detail));
                        },
                        render(data) {
                            if (this.chart) this.chart.destroy();
                            const ctx = this.$el.getContext('2d');
                            this.chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: data.labels,
                                    datasets: [
                                        { type:'line', label:'CSAT %', data:data.csat, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.15)', yAxisID:'y', tension:0.3, spanGaps:true },
                                        { type:'bar',  label:'Mensajes', data:data.volume, backgroundColor:'rgba(99,102,241,0.45)', borderRadius:4, yAxisID:'y1' },
                                    ],
                                },
                                options: {
                                    responsive:true, maintainAspectRatio:false,
                                    scales: {
                                        y:  { type:'linear', position:'left',  min:0, max:100, title:{display:true,text:'CSAT %'} },
                                        y1: { type:'linear', position:'right', min:0, grid:{drawOnChartArea:false}, title:{display:true,text:'Mensajes'} },
                                    },
                                },
                            });
                        }
                    }"></canvas>
            </div>
        </x-filament::section>
        </div>

        <div class="cm-chart-side">
        <x-filament::section>
            <x-slot name="heading">Origen de respuestas</x-slot>
            <x-slot name="description">Distribución por fuente del periodo.</x-slot>

            <div class="relative h-64 w-full">
                @if (count($sourceDonutData['values']) === 0)
                    <div class="flex h-full items-center justify-center">
                        <p class="text-sm text-zinc-400">Sin datos en el periodo.</p>
                    </div>
                @else
                    <canvas id="source-donut-chart"
                        x-data="{
                            chart: null,
                            init() {
                                this.render(@js($sourceDonutData));
                                window.addEventListener('chatbot-metrics-updated', e => this.render(e.detail.donut));
                            },
                            render(data) {
                                if (this.chart) this.chart.destroy();
                                const ctx = this.$el.getContext('2d');
                                this.chart = new Chart(ctx, {
                                    type: 'doughnut',
                                    data: { labels:data.labels, datasets:[{ data:data.values, backgroundColor:data.colors, borderWidth:2, borderRadius:3 }] },
                                    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{position:'bottom'} } },
                                });
                            }
                        }"></canvas>
                @endif
            </div>
        </x-filament::section>
        </div>
    </div>

    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
        @endpush
    @endonce

    {{-- ── Detalle por origen ───────────────────────────────────────── --}}
    <x-filament::section class="cm-section">
        <x-slot name="heading">Detalle por origen</x-slot>

        @php
            $labels = [
                'kb_high'   => ['KB alta confianza',     'background:#d1fae5;color:#065f46'],
                'kb_medium' => ['KB confianza media',    'background:#ecfccb;color:#3f6212'],
                'flow'      => ['Flujo guiado',          'background:#e0f2fe;color:#075985'],
                'llm'       => ['LLM (con contexto KB)', 'background:#e0e7ff;color:#3730a3'],
                'fallback'  => ['Fallback genérico',     'background:#fee2e2;color:#991b1b'],
                'system'    => ['Sistema / escalación',  'background:#f4f4f5;color:#52525b'],
                null        => ['Sin clasificar',        'background:#f4f4f5;color:#71717a'],
            ];
        @endphp

        @if (empty($sourceBreakdown))
            <p style="font-size:0.875rem;color:#71717a">Aún no hay respuestas registradas en este periodo.</p>
        @else
            <div style="overflow-x:auto">
                <table class="cm-table">
                    <thead>
                        <tr>
                            <th>Origen</th>
                            <th class="right">Total</th>
                            <th class="right">👍</th>
                            <th class="right">👎</th>
                            <th class="right">CSAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sourceBreakdown as $row)
                            @php
                                [$label, $badgeStyle] = $labels[$row['source_kind']] ?? ['—','background:#f4f4f5;color:#52525b'];
                                $rated = $row['helpful'] + $row['not_helpful'];
                                $csat  = $rated > 0 ? round(($row['helpful'] / $rated) * 100, 1) : null;
                                $csatColor = $csat === null ? '#a1a1aa' : ($csat >= 70 ? '#059669' : ($csat >= 40 ? '#d97706' : '#dc2626'));
                            @endphp
                            <tr>
                                <td><span class="cm-badge" style="{{ $badgeStyle }}">{{ $label }}</span></td>
                                <td class="right" style="font-weight:600">{{ $row['total'] }}</td>
                                <td class="right" style="font-weight:600;color:#059669">{{ $row['helpful'] }}</td>
                                <td class="right" style="font-weight:600;color:#dc2626">{{ $row['not_helpful'] }}</td>
                                <td class="right" style="font-weight:600;color:{{ $csatColor }}">{{ $csat !== null ? $csat.'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Top KB fallidos con drill-down ─────────────────────────── --}}
    <x-filament::section class="cm-section">
        <x-slot name="heading">Artículos KB que están fallando</x-slot>
        <x-slot name="description">Click en una fila para ver los mensajes específicos que recibieron 👎 con ese artículo.</x-slot>

        @if (empty($topUnhelpful))
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;color:#059669">
                <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                No hay artículos con votos negativos aún.
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="cm-table">
                    <thead>
                        <tr>
                            <th>Artículo</th>
                            <th class="right">Usado</th>
                            <th class="right">👍</th>
                            <th class="right">👎</th>
                            <th class="right">CSAT</th>
                            <th class="center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topUnhelpful as $row)
                            @php
                                $csatColor = $row['csat'] === null ? '#a1a1aa' : ($row['csat'] >= 60 ? '#059669' : ($row['csat'] >= 30 ? '#d97706' : '#dc2626'));
                            @endphp
                            <tr>
                                <td>{{ $row['title'] ?? '— (artículo eliminado #'.$row['article_id'].')' }}</td>
                                <td class="right" style="font-weight:600">{{ $row['total'] }}</td>
                                <td class="right" style="font-weight:600;color:#059669">{{ $row['helpful'] }}</td>
                                <td class="right" style="font-weight:600;color:#dc2626">{{ $row['not_helpful'] }}</td>
                                <td class="right" style="font-weight:600;color:{{ $csatColor }}">{{ $row['csat'] !== null ? $row['csat'].'%' : '—' }}</td>
                                <td class="center">
                                    <button type="button" wire:click="showDrilldown({{ $row['article_id'] }})"
                                        style="display:inline-flex;align-items:center;gap:0.25rem;border-radius:0.5rem;background:#f4f4f5;padding:0.25rem 0.625rem;font-size:0.75rem;font-weight:500;color:#3f3f46;border:none;cursor:pointer">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon-sm" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                                        Detalle
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Modal drill-down ────────────────────────────────────────── --}}
    @if ($drilldownArticle)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/60 p-4 backdrop-blur-sm"
             wire:click.self="closeDrilldown">
            <div class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl dark:bg-zinc-900"
                 style="animation: cmFadeUp .2s ease both">
                <div class="sticky top-0 flex items-start justify-between gap-4 border-b border-zinc-200 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div>
                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $drilldownArticle->title }}</h3>
                        <p class="mt-0.5 text-xs text-zinc-400">Mensajes 👎 recibidos con este artículo</p>
                    </div>
                    <button type="button" wire:click="closeDrilldown"
                        style="border-radius:0.5rem;padding:0.375rem;color:#a1a1aa;border:none;background:transparent;cursor:pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-3 p-6 text-sm">
                    @if (empty($drilldownMessages))
                        <p class="text-center text-zinc-400">Sin mensajes negativos en el periodo.</p>
                    @else
                        @foreach ($drilldownMessages as $msg)
                            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50">
                                @if ($msg['question'])
                                    <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                                        <div class="mb-1 text-xs font-semibold uppercase text-zinc-400">Usuario preguntó:</div>
                                        <p class="text-zinc-800 dark:text-zinc-200">{{ $msg['question'] }}</p>
                                    </div>
                                @endif
                                <div class="px-4 py-3">
                                    <div class="mb-1 text-xs font-semibold uppercase text-zinc-400">Bot respondió:</div>
                                    <p class="text-zinc-600 dark:text-zinc-400">{{ $msg['answer'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-200 bg-zinc-100/60 px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-800">
                                    <p class="text-xs text-zinc-400">
                                        @if ($msg['voted_at'])
                                            Voto: {{ $msg['voted_at']->translatedFormat('d M Y · H:i') }}
                                        @endif
                                    </p>
                                    <button type="button"
                                        wire:click="createReviewTicket({{ $msg['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="createReviewTicket({{ $msg['id'] }})"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-200 dark:hover:bg-amber-900/60">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon-sm" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg>
                                        Crear ticket de revisión
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── Gaps de KB ───────────────────────────────────────────────── --}}
    <x-filament::section class="cm-section">
        <x-slot name="heading">Gaps de KB — preguntas que el bot no supo responder</x-slot>
        <x-slot name="description">Cada pregunta representa un artículo KB por escribir. Click en el botón para crearlo con el título pre-rellenado.</x-slot>

        @if (empty($fallbackQuestions))
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;color:#059669">
                <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                El bot está cubriendo todo.
            </div>
        @else
            <ul style="font-size:0.875rem;border-top:1px solid #f4f4f5">
                @foreach ($fallbackQuestions as $row)
                    <li style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:0.625rem 0;border-bottom:1px solid #f4f4f5">
                        <span style="flex:1;color:#3f3f46">"{{ $row['question'] }}"</span>
                        <span style="flex-shrink:0;border-radius:9999px;background:#fee2e2;padding:0.125rem 0.5rem;font-size:0.75rem;font-weight:600;color:#b91c1c">
                            {{ $row['count'] }}×
                        </span>
                        <a href="{{ $this->createKbFromGapUrl($row['question']) }}" target="_blank"
                            style="display:inline-flex;flex-shrink:0;align-items:center;gap:0.375rem;border-radius:0.5rem;background:#059669;padding:0.25rem 0.625rem;font-size:0.75rem;font-weight:600;color:#fff;text-decoration:none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="cm-icon-sm" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/></svg>
                            Crear KB
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    {{-- ── Últimos negativos ────────────────────────────────────────── --}}
    <x-filament::section class="cm-section">
        <x-slot name="heading">Últimos 10 mensajes marcados como "no me sirvió"</x-slot>

        @if (empty($recentNegatives))
            <p style="font-size:0.875rem;color:#a1a1aa">No hay mensajes negativos recientes.</p>
        @else
            @foreach ($recentNegatives as $row)
                <div class="cm-neg-item">
                    <div class="cm-neg-meta">
                        <span>{{ $row['created_at']->translatedFormat('d M Y · H:i') }}</span>
                        @if ($row['kb_title'])
                            <span style="border-radius:9999px;background:#e4e4e7;padding:0.15rem 0.5rem;font-weight:500;color:#52525b">
                                KB: {{ Str::limit($row['kb_title'], 40) }}
                            </span>
                        @endif
                    </div>
                    <p class="cm-neg-body">{{ Str::of($row['content'])->replaceMatches('/#{1,6}\s+/', '')->replaceMatches('/\*{1,2}([^*]+)\*{1,2}/', '$1')->limit(300) }}</p>
                </div>
            @endforeach
        @endif
    </x-filament::section>
</x-filament-panels::page>
