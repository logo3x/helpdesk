<x-filament-panels::page>

    <style>
        @keyframes cmFadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .cm-kpi  { animation: cmFadeUp .3s ease both; opacity: 0; }
        .cm-section { animation: cmFadeUp .35s ease .1s both; }
    </style>

    {{-- ── Controles: ventana + filtro depto + export ─────────────── --}}
    <div class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Datos del chatbot en los últimos <strong>{{ $window }}</strong> días.
            @if ($selectedDepartmentId)
                · Filtrado por departamento.
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="window"
                class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm transition focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-200 dark:border-zinc-700 dark:bg-zinc-900">
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </select>

            <select wire:model.live="departmentId"
                class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm transition focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-200 dark:border-zinc-700 dark:bg-zinc-900">
                <option value="">Todos los departamentos</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>

            <button type="button" wire:click="exportExcel"
                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700 active:scale-95">
                <x-heroicon-m-arrow-down-tray class="size-4" />
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
            'sky'     => ['bg'=>'bg-sky-50 dark:bg-sky-950/40',     'icon'=>'text-sky-500'],
            'indigo'  => ['bg'=>'bg-indigo-50 dark:bg-indigo-950/40','icon'=>'text-indigo-500'],
            'amber'   => ['bg'=>'bg-amber-50 dark:bg-amber-950/40', 'icon'=>'text-amber-500'],
            'emerald' => ['bg'=>'bg-emerald-50 dark:bg-emerald-950/40','icon'=>'text-emerald-500'],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
         x-data="{}"
         x-init="document.querySelectorAll('.cm-kpi').forEach((el,i)=>{ el.style.animationDelay=(i*60)+'ms'; })">
        @foreach ($kpis as $kpi)
            @php $c = $colorMap[$kpi['color']]; @endphp
            <div class="cm-kpi overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
                <div class="p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg {{ $c['bg'] }}">
                            <x-dynamic-component :component="'heroicon-o-'.$kpi['icon']" class="size-5 {{ $c['icon'] }}" />
                        </div>
                        @if ($kpi['delta'])
                            @php
                                $dir = $kpi['delta']['direction'];
                                $pct = $kpi['delta']['pct'];
                                $color = match(true) {
                                    $dir === 'flat' => 'text-zinc-400 bg-zinc-50 dark:bg-zinc-800',
                                    $dir === 'up'   => 'text-emerald-700 bg-emerald-50 dark:bg-emerald-950/50',
                                    default         => 'text-rose-700 bg-rose-50 dark:bg-rose-950/50',
                                };
                                $arrow = match($dir) { 'up'=>'↑','down'=>'↓',default=>'→' };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $color }}">
                                {{ $arrow }} {{ abs($pct) }}%
                            </span>
                        @endif
                    </div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                        @if ($kpi['value'] !== null)
                            {{ $kpi['value'] }}{{ $kpi['suffix'] }}
                        @else
                            <span class="text-zinc-300">—</span>
                        @endif
                    </div>
                    <div class="mt-0.5 text-sm font-medium text-zinc-500">{{ $kpi['label'] }}</div>
                    <div class="mt-1 text-xs text-zinc-400">{{ $kpi['hint'] }} · vs periodo anterior</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Gráficos ─────────────────────────────────────────────────── --}}
    <div class="cm-section grid gap-4 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-2">
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
