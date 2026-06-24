<x-filament-panels::page>

    <style>
        @keyframes slaFadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .sla-kpi     { animation: slaFadeUp .3s ease both; opacity: 0; }
        .sla-section { animation: slaFadeUp .35s ease .1s both; }
    </style>

    {{-- ── Selector de ventana ──────────────────────────────────────── --}}
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Cumplimiento de SLA en los últimos <strong>{{ $window }}</strong> días.
        </p>
        <select wire:model.live="window"
            class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm transition focus:border-sky-400 focus:outline-none focus:ring-1 focus:ring-sky-200 dark:border-zinc-700 dark:bg-zinc-900">
            <option value="7">Últimos 7 días</option>
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 90 días</option>
            <option value="365">Último año</option>
        </select>
    </div>

    {{-- ── KPI cards globales ─────────────────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-3"
         x-data="{}"
         x-init="document.querySelectorAll('.sla-kpi').forEach((el,i)=>{ el.style.animationDelay=(i*60)+'ms'; })">

        <div class="sla-kpi overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-950/40">
                <x-heroicon-o-ticket class="size-5 text-sky-500" />
            </div>
            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $summary['resolved'] }}</div>
            <div class="mt-0.5 text-sm font-medium text-zinc-500">Tickets resueltos</div>
            <div class="mt-1 text-xs text-zinc-400">Con SLA configurado</div>
        </div>

        <div class="sla-kpi overflow-hidden rounded-xl border bg-white p-5 shadow-sm dark:bg-zinc-900/80
            {{ $summary['breached'] > 0
                ? 'border-rose-200 dark:border-rose-800/60'
                : 'border-zinc-200/80 dark:border-zinc-700/80' }}">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg {{ $summary['breached'] > 0 ? 'bg-rose-50 dark:bg-rose-950/40' : 'bg-emerald-50 dark:bg-emerald-950/40' }}">
                <x-heroicon-o-exclamation-triangle class="size-5 {{ $summary['breached'] > 0 ? 'text-rose-500' : 'text-emerald-500' }}" />
            </div>
            <div class="text-2xl font-bold {{ $summary['breached'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ $summary['breached'] }}
            </div>
            <div class="mt-0.5 text-sm font-medium text-zinc-500">SLA quebrados</div>
            <div class="mt-1 text-xs text-zinc-400">
                {{ $summary['resolved'] > 0 ? round(($summary['breached'] / $summary['resolved']) * 100, 1).'%' : '0%' }} de los resueltos
            </div>
        </div>

        <div class="sla-kpi overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="mb-2 flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-950/40">
                <x-heroicon-o-check-badge class="size-5 text-emerald-500" />
            </div>
            @if ($summary['compliance'] !== null)
                <div class="text-2xl font-bold {{ $summary['compliance'] >= 90 ? 'text-emerald-600' : ($summary['compliance'] >= 70 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $summary['compliance'] }}%
                </div>
                <div class="mt-0.5 text-sm font-medium text-zinc-500">Cumplimiento</div>
                <div class="mt-1 text-xs text-zinc-400">Resueltos sin breach</div>
            @else
                <div class="text-2xl font-bold text-zinc-300">—</div>
                <div class="mt-0.5 text-sm font-medium text-zinc-500">Cumplimiento</div>
                <div class="mt-1 text-xs text-zinc-400">Sin tickets resueltos aún</div>
            @endif
        </div>
    </div>

    {{-- ── Tickets en riesgo ─────────────────────────────────────────── --}}
    <x-filament::section class="sla-section">
        <x-slot name="heading">Tickets en riesgo · vencen en las próximas 24h o ya vencidos</x-slot>
        <x-slot name="description">
            Tickets NO resueltos con SLA configurado. Permite intervenir antes de que el breach quede registrado.
        </x-slot>

        @if ($atRisk->isEmpty())
            <div class="flex items-center gap-2 text-sm text-emerald-600">
                <x-heroicon-o-check-circle class="size-4" />
                Sin tickets en riesgo. Todos los SLA abiertos están holgados.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Departamento</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Asignado</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Prioridad</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Vence en</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($atRisk as $row)
                            @php($t = $row['ticket'])
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5">
                                    <a href="{{ route('filament.admin.resources.tickets.view', ['record' => $t->id]) }}"
                                       class="font-mono text-xs font-semibold text-primary-600 hover:underline">
                                        {{ $t->number }}
                                    </a>
                                    <div class="mt-0.5 text-xs text-zinc-400">{{ Str::limit($t->subject, 50) }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $t->department?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $t->assignee?->name ?? '— Sin asignar —' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        @switch($t->priority->value)
                                            @case('critica')  bg-red-100    text-red-800    dark:bg-red-950/50    dark:text-red-300    @break
                                            @case('alta')     bg-orange-100 text-orange-800 dark:bg-orange-950/50 dark:text-orange-300 @break
                                            @case('media')    bg-amber-100  text-amber-800  dark:bg-amber-950/50  dark:text-amber-300  @break
                                            @default          bg-zinc-100   text-zinc-700   dark:bg-zinc-800      dark:text-zinc-400
                                        @endswitch">
                                        {{ $t->priority->getLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    @if ($row['is_breached'])
                                        <span class="font-semibold text-rose-600">Vencido hace {{ abs($row['hours_left']) }}h</span>
                                    @else
                                        <span class="font-semibold {{ $row['hours_left'] <= 4 ? 'text-rose-600' : ($row['hours_left'] <= 12 ? 'text-amber-600' : 'text-zinc-600') }}">
                                            {{ $row['hours_left'] }}h
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Matriz cumplimiento dept × prioridad ─────────────────────── --}}
    <x-filament::section class="sla-section">
        <x-slot name="heading">Cumplimiento SLA por departamento ({{ $window }} días)</x-slot>
        <x-slot name="description">
            Cada celda muestra el porcentaje de tickets resueltos sin breach del cruce departamento × prioridad.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                        <th class="px-3 py-2.5 text-left font-semibold">Departamento</th>
                        @foreach ($priorities as $p)
                            <th class="px-3 py-2.5 text-center font-semibold">{{ $p->getLabel() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report as $row)
                        <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                            <td class="px-3 py-2.5 font-semibold text-zinc-700 dark:text-zinc-300">{{ $row['department'] }}</td>
                            @foreach ($row['priorities'] as $p)
                                <td class="px-3 py-2.5 text-center">
                                    @if ($p['total'] > 0)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold
                                            {{ $p['compliance'] >= 90
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : ($p['compliance'] >= 70
                                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
                                                    : 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300') }}">
                                            {{ $p['compliance'] }}%
                                        </span>
                                        <div class="mt-0.5 text-[10px] text-zinc-400">
                                            {{ $p['total'] }} ticket{{ $p['total'] !== 1 ? 's' : '' }}
                                            @if ($p['breached'] > 0)
                                                · <span class="text-rose-500">{{ $p['breached'] }} breach</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-300 dark:text-zinc-600">Sin datos</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ── Últimas escalaciones ──────────────────────────────────────── --}}
    <x-filament::section class="sla-section">
        <x-slot name="heading">Últimas escalaciones</x-slot>

        @if ($escalations->isEmpty())
            <p class="text-sm text-zinc-400">No hay escalaciones recientes.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Tipo</th>
                            <th class="px-3 py-2.5 text-center font-semibold">SLA (min)</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Transcurrido</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Notificado a</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Cuándo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($escalations as $esc)
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5">
                                    <span class="font-mono text-xs font-semibold">{{ $esc->ticket?->number }}</span>
                                    <div class="mt-0.5 text-xs text-zinc-400">{{ Str::limit($esc->ticket?->subject, 40) }}</div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        {{ str_contains($esc->type, 'breach')
                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                        {{ str_replace('_', ' ', $esc->type) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center font-mono text-xs">{{ $esc->sla_minutes }}</td>
                                <td class="px-3 py-2.5 text-center font-mono text-xs">{{ $esc->elapsed_minutes }}</td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $esc->notifiedUser?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-zinc-400">{{ $esc->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
