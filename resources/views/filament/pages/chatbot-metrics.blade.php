<x-filament-panels::page>
    {{-- ── Controles: ventana + filtro depto + export ────────────── --}}
    <div class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Datos del chatbot en los últimos <strong>{{ $window }}</strong> días.
            @if ($selectedDepartmentId)
                · Filtrado por departamento.
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <select
                wire:model.live="window"
                class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
                <option value="7">Últimos 7 días</option>
                <option value="30">Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
            </select>

            <select
                wire:model.live="departmentId"
                class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            >
                <option value="">Todos los departamentos</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>

            <button
                type="button"
                wire:click="exportExcel"
                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
            >
                📥 Exportar a Excel
            </button>
        </div>
    </div>

    {{-- ── KPI cards con comparativa periodo anterior ─────────────── --}}
    @php
        $deltaSessions = $this->delta($summary['sessions'], $summaryPrev['sessions']);
        $deltaMessages = $this->delta($summary['assistant_messages'], $summaryPrev['assistant_messages']);
        $deltaCsat = $this->delta($summary['csat_pct'], $summaryPrev['csat_pct']);
        $deltaAuto = $this->delta($summary['auto_resolution_pct'], $summaryPrev['auto_resolution_pct']);
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $kpis = [
                ['label' => 'Sesiones', 'value' => $summary['sessions'], 'suffix' => '', 'delta' => $deltaSessions, 'hint' => 'Conversaciones iniciadas'],
                ['label' => 'Mensajes del bot', 'value' => $summary['assistant_messages'], 'suffix' => '', 'delta' => $deltaMessages, 'hint' => 'Respuestas dadas'],
                ['label' => 'CSAT', 'value' => $summary['csat_pct'], 'suffix' => '%', 'delta' => $deltaCsat, 'hint' => $summary['helpful'].' 👍 · '.$summary['not_helpful'].' 👎'],
                ['label' => 'Auto-resolución', 'value' => $summary['auto_resolution_pct'], 'suffix' => '%', 'delta' => $deltaAuto, 'hint' => 'Sin escalar a ticket'],
            ];
        @endphp

        @foreach ($kpis as $kpi)
            <x-filament::section compact>
                <x-slot name="heading">{{ $kpi['label'] }}</x-slot>
                <div class="flex items-baseline gap-2">
                    @if ($kpi['value'] !== null)
                        <span class="text-3xl font-semibold">{{ $kpi['value'] }}{{ $kpi['suffix'] }}</span>
                    @else
                        <span class="text-3xl font-semibold text-zinc-400">—</span>
                    @endif

                    @if ($kpi['delta'])
                        @php
                            $dir = $kpi['delta']['direction'];
                            $pct = $kpi['delta']['pct'];
                            $isImprovement = ($kpi['label'] === 'CSAT' || $kpi['label'] === 'Auto-resolución')
                                ? $dir === 'up'
                                : $dir === 'up';
                            $color = match (true) {
                                $dir === 'flat' => 'text-zinc-400',
                                $isImprovement => 'text-emerald-600',
                                default => 'text-rose-600',
                            };
                            $arrow = match ($dir) {
                                'up' => '↑',
                                'down' => '↓',
                                default => '→',
                            };
                        @endphp
                        <span class="text-xs font-medium {{ $color }}">
                            {{ $arrow }} {{ abs($pct) }}%
                        </span>
                    @endif
                </div>
                <p class="text-xs text-zinc-500">{{ $kpi['hint'] }} · vs periodo anterior</p>
            </x-filament::section>
        @endforeach
    </div>

    {{-- ── Gráficos: CSAT trend (línea) + breakdown (dona) ─────────── --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Evolución del CSAT y volumen</x-slot>
            <x-slot name="description">Línea naranja = % CSAT diario · Barras azules = mensajes del bot por día.</x-slot>

            <div class="relative h-64 w-full">
                <canvas
                    id="csat-trend-chart"
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
                                        {
                                            type: 'line',
                                            label: 'CSAT %',
                                            data: data.csat,
                                            borderColor: '#f59e0b',
                                            backgroundColor: 'rgba(245,158,11,0.15)',
                                            yAxisID: 'y',
                                            tension: 0.3,
                                            spanGaps: true,
                                        },
                                        {
                                            type: 'bar',
                                            label: 'Mensajes',
                                            data: data.volume,
                                            backgroundColor: 'rgba(59,130,246,0.5)',
                                            yAxisID: 'y1',
                                        },
                                    ],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: { type: 'linear', position: 'left', min: 0, max: 100, title: { display: true, text: 'CSAT %' } },
                                        y1: { type: 'linear', position: 'right', min: 0, grid: { drawOnChartArea: false }, title: { display: true, text: 'Mensajes' } },
                                    },
                                },
                            });
                        }
                    }"
                ></canvas>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Origen de respuestas</x-slot>
            <x-slot name="description">Distribución por fuente del periodo.</x-slot>

            <div class="relative h-64 w-full">
                @if (count($sourceDonutData['values']) === 0)
                    <p class="flex h-full items-center justify-center text-sm text-zinc-400">Sin datos en el periodo.</p>
                @else
                    <canvas
                        id="source-donut-chart"
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
                                    data: {
                                        labels: data.labels,
                                        datasets: [{
                                            data: data.values,
                                            backgroundColor: data.colors,
                                            borderWidth: 1,
                                        }],
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: { legend: { position: 'bottom' } },
                                    },
                                });
                            }
                        }"
                    ></canvas>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- Chart.js cargado vía CDN (lazy) — el panel Filament ya carga Chart.js
         si hay algún widget de tipo chart en la página, pero como esta es una
         page custom sin widgets, lo importamos a mano. Versión 4.4 estable. --}}
    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
        @endpush
    @endonce

    {{-- ── Distribución de respuestas (tabla detalle) ─────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Detalle por origen</x-slot>

        @php
            $labels = [
                'kb_high' => ['KB alta confianza', 'bg-emerald-100 text-emerald-700'],
                'kb_medium' => ['KB confianza media', 'bg-lime-100 text-lime-700'],
                'flow' => ['Flujo guiado', 'bg-sky-100 text-sky-700'],
                'llm' => ['LLM (con contexto KB)', 'bg-indigo-100 text-indigo-700'],
                'fallback' => ['Fallback genérico', 'bg-rose-100 text-rose-700'],
                'system' => ['Sistema / escalación', 'bg-gray-100 text-gray-700'],
                null => ['Sin clasificar', 'bg-gray-100 text-gray-500'],
            ];
        @endphp

        @if (empty($sourceBreakdown))
            <p class="text-sm text-zinc-500">Aún no hay respuestas registradas en este periodo.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left font-medium">Origen</th>
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                            <th class="px-3 py-2 text-right font-medium">👍</th>
                            <th class="px-3 py-2 text-right font-medium">👎</th>
                            <th class="px-3 py-2 text-right font-medium">CSAT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sourceBreakdown as $row)
                            @php
                                [$label, $color] = $labels[$row['source_kind']] ?? ['—', 'bg-gray-100 text-gray-700'];
                                $rated = $row['helpful'] + $row['not_helpful'];
                                $csat = $rated > 0 ? round(($row['helpful'] / $rated) * 100, 1) : null;
                            @endphp
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">{{ $label }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-medium">{{ $row['total'] }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600">{{ $row['helpful'] }}</td>
                                <td class="px-3 py-2 text-right text-rose-600">{{ $row['not_helpful'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $csat !== null ? $csat.'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Top KB fallidos con DRILL-DOWN ─────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Artículos KB que están fallando</x-slot>
        <x-slot name="description">
            Click en una fila para ver los mensajes específicos que recibieron 👎 con ese artículo.
        </x-slot>

        @if (empty($topUnhelpful))
            <p class="text-sm text-zinc-500">No hay artículos con votos negativos aún. 🎉</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left font-medium">Artículo</th>
                            <th class="px-3 py-2 text-right font-medium">Usado</th>
                            <th class="px-3 py-2 text-right font-medium">👍</th>
                            <th class="px-3 py-2 text-right font-medium">👎</th>
                            <th class="px-3 py-2 text-right font-medium">CSAT</th>
                            <th class="px-3 py-2 text-center font-medium">Ver mensajes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topUnhelpful as $row)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-900/50">
                                <td class="px-3 py-2">{{ $row['title'] ?? '— (artículo eliminado #'.$row['article_id'].')' }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ $row['total'] }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600">{{ $row['helpful'] }}</td>
                                <td class="px-3 py-2 text-right text-rose-600">{{ $row['not_helpful'] }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($row['csat'] !== null)
                                        <span class="{{ $row['csat'] >= 60 ? 'text-emerald-600' : ($row['csat'] >= 30 ? 'text-amber-600' : 'text-rose-600') }}">
                                            {{ $row['csat'] }}%
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        wire:click="showDrilldown({{ $row['article_id'] }})"
                                        class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-1 text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                                    >
                                        🔍 Drill-down
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Modal drill-down ──────────────────────────────────────── --}}
    @if ($drilldownArticle)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/50 p-4"
            wire:click.self="closeDrilldown"
        >
            <div class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">{{ $drilldownArticle->title }}</h3>
                        <p class="text-sm text-zinc-500">Mensajes 👎 recibidos con este artículo</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeDrilldown"
                        class="rounded p-1 text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-4 space-y-3 text-sm">
                    @if (empty($drilldownMessages))
                        <p class="text-zinc-500">Sin mensajes negativos en el periodo.</p>
                    @else
                        @foreach ($drilldownMessages as $msg)
                            <div class="rounded-md border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                                @if ($msg['question'])
                                    <div class="mb-2">
                                        <span class="text-xs font-semibold uppercase text-zinc-500">Usuario preguntó:</span>
                                        <p class="mt-1 text-zinc-800 dark:text-zinc-200">{{ $msg['question'] }}</p>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-xs font-semibold uppercase text-zinc-500">Bot respondió:</span>
                                    <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $msg['answer'] }}</p>
                                </div>
                                @if ($msg['voted_at'])
                                    <p class="mt-2 text-xs text-zinc-400">Voto: {{ $msg['voted_at']->translatedFormat('d M Y · H:i') }}</p>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- ── Gaps de KB con BOTÓN "Crear artículo con esta pregunta" ── --}}
    <x-filament::section>
        <x-slot name="heading">Gaps de KB — preguntas que el bot no supo responder</x-slot>
        <x-slot name="description">
            Cada pregunta representa un artículo KB por escribir. Click en el botón para crearlo con el título pre-rellenado.
        </x-slot>

        @if (empty($fallbackQuestions))
            <p class="text-sm text-zinc-500">El bot está cubriendo todo. 🎉</p>
        @else
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($fallbackQuestions as $row)
                    <li class="flex items-center justify-between gap-4 py-2">
                        <span class="flex-1 text-zinc-700 dark:text-zinc-200">"{{ $row['question'] }}"</span>
                        <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">
                            {{ $row['count'] }}×
                        </span>
                        <a
                            href="{{ $this->createKbFromGapUrl($row['question']) }}"
                            target="_blank"
                            class="shrink-0 rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700"
                        >
                            ✍️ Crear KB
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    {{-- ── Últimos 👎 detallados ──────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Últimos 10 mensajes marcados como "no me sirvió"</x-slot>

        @if (empty($recentNegatives))
            <p class="text-sm text-zinc-500">No hay mensajes negativos recientes.</p>
        @else
            <ul class="space-y-3 text-sm">
                @foreach ($recentNegatives as $row)
                    <li class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ $row['created_at']->translatedFormat('d M Y · H:i') }}</span>
                            @if ($row['kb_title'])
                                <span>KB: <strong>{{ $row['kb_title'] }}</strong></span>
                            @endif
                        </div>
                        <p class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $row['content'] }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
