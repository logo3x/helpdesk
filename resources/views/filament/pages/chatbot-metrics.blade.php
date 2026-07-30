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
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            .cm-chart-grid { grid-template-columns: 2fr 1fr; }
        }
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
                'kb_high'   => ['KB alta confianza',     'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'],
                'kb_medium' => ['KB confianza media',    'bg-lime-100 text-lime-700 dark:bg-lime-950/50 dark:text-lime-300'],
                'flow'      => ['Flujo guiado',          'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300'],
                'llm'       => ['LLM (con contexto KB)', 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300'],
                'fallback'  => ['Fallback genérico',     'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'],
                'system'    => ['Sistema / escalación',  'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'],
                null        => ['Sin clasificar',        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-500'],
            ];
        @endphp

        @if (empty($sourceBreakdown))
            <p class="text-sm text-zinc-500">Aún no hay respuestas registradas en este periodo.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Origen</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Total</th>
                            <th class="px-3 py-2.5 text-right font-semibold">👍</th>
                            <th class="px-3 py-2.5 text-right font-semibold">👎</th>
                            <th class="px-3 py-2.5 text-right font-semibold">CSAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sourceBreakdown as $row)
                            @php
                                [$label, $color] = $labels[$row['source_kind']] ?? ['—','bg-zinc-100 text-zinc-600'];
                                $rated = $row['helpful'] + $row['not_helpful'];
                                $csat  = $rated > 0 ? round(($row['helpful'] / $rated) * 100, 1) : null;
                            @endphp
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $color }}">{{ $label }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ $row['total'] }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-emerald-600">{{ $row['helpful'] }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-rose-600">{{ $row['not_helpful'] }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    @if ($csat !== null)
                                        <span class="{{ $csat >= 70 ? 'text-emerald-600' : ($csat >= 40 ? 'text-amber-600' : 'text-rose-600') }} font-semibold">
                                            {{ $csat }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
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
            <div class="flex items-center gap-2 text-sm text-emerald-600">
                <x-heroicon-o-check-circle class="size-4" />
                No hay artículos con votos negativos aún.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Artículo</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Usado</th>
                            <th class="px-3 py-2.5 text-right font-semibold">👍</th>
                            <th class="px-3 py-2.5 text-right font-semibold">👎</th>
                            <th class="px-3 py-2.5 text-right font-semibold">CSAT</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topUnhelpful as $row)
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5 text-zinc-700 dark:text-zinc-300">
                                    {{ $row['title'] ?? '— (artículo eliminado #'.$row['article_id'].')' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold">{{ $row['total'] }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-emerald-600">{{ $row['helpful'] }}</td>
                                <td class="px-3 py-2.5 text-right font-semibold text-rose-600">{{ $row['not_helpful'] }}</td>
                                <td class="px-3 py-2.5 text-right">
                                    @if ($row['csat'] !== null)
                                        <span class="font-semibold {{ $row['csat'] >= 60 ? 'text-emerald-600' : ($row['csat'] >= 30 ? 'text-amber-600' : 'text-rose-600') }}">
                                            {{ $row['csat'] }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <button type="button" wire:click="showDrilldown({{ $row['article_id'] }})"
                                        class="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2.5 py-1 text-xs font-medium text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                                        <x-heroicon-m-magnifying-glass class="size-3" />
                                        Drill-down
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
                        class="rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800">
                        <x-heroicon-m-x-mark class="size-4" />
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
                                        <x-heroicon-m-ticket class="size-3.5" />
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
            <div class="flex items-center gap-2 text-sm text-emerald-600">
                <x-heroicon-o-check-circle class="size-4" />
                El bot está cubriendo todo.
            </div>
        @else
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($fallbackQuestions as $row)
                    <li class="flex items-center justify-between gap-4 py-2.5">
                        <span class="flex-1 text-zinc-700 dark:text-zinc-200">"{{ $row['question'] }}"</span>
                        <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">
                            {{ $row['count'] }}×
                        </span>
                        <a href="{{ $this->createKbFromGapUrl($row['question']) }}" target="_blank"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white transition hover:bg-emerald-700">
                            <x-heroicon-m-pencil-square class="size-3" />
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
            <p class="text-sm text-zinc-400">No hay mensajes negativos recientes.</p>
        @else
            <ul class="space-y-2.5 text-sm">
                @foreach ($recentNegatives as $row)
                    <li class="overflow-hidden rounded-xl border border-zinc-200/80 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/60">
                        <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-2 text-xs text-zinc-400 dark:border-zinc-800">
                            <span>{{ $row['created_at']->translatedFormat('d M Y · H:i') }}</span>
                            @if ($row['kb_title'])
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    KB: {{ $row['kb_title'] }}
                                </span>
                            @endif
                        </div>
                        <p class="px-4 py-3 text-zinc-700 dark:text-zinc-200">{{ $row['content'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
