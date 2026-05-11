<x-filament-panels::page>
    {{-- ── Selector de ventana ───────────────────────────────────── --}}
    <div class="flex items-center justify-between gap-4">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Datos del chatbot en los últimos <strong>{{ $window }}</strong> días.
        </p>
        <select
            wire:model.live="window"
            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900"
        >
            <option value="7">Últimos 7 días</option>
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 90 días</option>
            <option value="365">Último año</option>
        </select>
    </div>

    {{-- ── KPI cards ──────────────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-filament::section compact>
            <x-slot name="heading">Sesiones</x-slot>
            <div class="text-3xl font-semibold">{{ $summary['sessions'] }}</div>
            <p class="text-xs text-zinc-500">Conversaciones iniciadas</p>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Mensajes del bot</x-slot>
            <div class="text-3xl font-semibold">{{ $summary['assistant_messages'] }}</div>
            <p class="text-xs text-zinc-500">Total de respuestas dadas</p>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">CSAT</x-slot>
            @if ($summary['csat_pct'] !== null)
                <div class="text-3xl font-semibold {{ $summary['csat_pct'] >= 80 ? 'text-emerald-600' : ($summary['csat_pct'] >= 60 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $summary['csat_pct'] }}%
                </div>
                <p class="text-xs text-zinc-500">{{ $summary['helpful'] }} 👍 · {{ $summary['not_helpful'] }} 👎 ({{ $summary['rated'] }} votos)</p>
            @else
                <div class="text-3xl font-semibold text-zinc-400">—</div>
                <p class="text-xs text-zinc-500">Sin feedback aún</p>
            @endif
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Auto-resolución</x-slot>
            @if ($summary['auto_resolution_pct'] !== null)
                <div class="text-3xl font-semibold">{{ $summary['auto_resolution_pct'] }}%</div>
                <p class="text-xs text-zinc-500">Sin escalar a ticket</p>
            @else
                <div class="text-3xl font-semibold text-zinc-400">—</div>
                <p class="text-xs text-zinc-500">Sin datos</p>
            @endif
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Cobertura</x-slot>
            @php
                $rated = $summary['rated'];
                $messages = $summary['assistant_messages'];
                $coverage = $messages > 0 ? round(($rated / $messages) * 100, 1) : null;
            @endphp
            @if ($coverage !== null)
                <div class="text-3xl font-semibold">{{ $coverage }}%</div>
                <p class="text-xs text-zinc-500">Mensajes con voto</p>
            @else
                <div class="text-3xl font-semibold text-zinc-400">—</div>
                <p class="text-xs text-zinc-500">Sin actividad</p>
            @endif
        </x-filament::section>
    </div>

    {{-- ── Distribución por origen ────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Origen de las respuestas</x-slot>
        <x-slot name="description">
            Indica de dónde vino cada respuesta: KB con alta/media confianza, flujo guiado,
            LLM (con o sin KB), o fallback genérico. Demasiados "fallback" o "llm sin KB"
            implican que la KB tiene huecos.
        </x-slot>

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
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $color }}">
                                        {{ $label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right font-medium">{{ $row['total'] }}</td>
                                <td class="px-3 py-2 text-right text-emerald-600">{{ $row['helpful'] }}</td>
                                <td class="px-3 py-2 text-right text-rose-600">{{ $row['not_helpful'] }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ $csat !== null ? $csat.'%' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Top KB con votos negativos ─────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Artículos KB que están fallando</x-slot>
        <x-slot name="description">
            Top 10 artículos con más votos negativos en el periodo. Si un artículo
            recibe muchos 👎 probablemente está desactualizado, mal explicado o no
            cubre el caso que los usuarios buscan.
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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topUnhelpful as $row)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-3 py-2">
                                    {{ $row['title'] ?? '— (artículo eliminado #'.$row['article_id'].')' }}
                                </td>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Gaps de KB (preguntas que terminaron en fallback) ─────── --}}
    <x-filament::section>
        <x-slot name="heading">Gaps de KB — preguntas que el bot no supo responder</x-slot>
        <x-slot name="description">
            Top 10 preguntas más repetidas que terminaron en fallback genérico.
            Cada una representa un artículo KB que probablemente vale la pena escribir.
        </x-slot>

        @if (empty($fallbackQuestions))
            <p class="text-sm text-zinc-500">El bot está cubriendo todo. 🎉</p>
        @else
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @foreach ($fallbackQuestions as $row)
                    <li class="flex items-center justify-between gap-4 py-2">
                        <span class="text-zinc-700 dark:text-zinc-200">"{{ $row['question'] }}"</span>
                        <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">
                            {{ $row['count'] }}×
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    {{-- ── Últimos 👎 detallados ──────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Últimos 10 mensajes marcados como "no me sirvió"</x-slot>
        <x-slot name="description">
            Vista rápida para entender el contexto de las quejas más recientes.
        </x-slot>

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
