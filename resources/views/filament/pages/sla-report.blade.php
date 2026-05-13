<x-filament-panels::page>
    {{-- ── Selector de ventana + resumen global ─────────────────── --}}
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Cumplimiento de SLA en los últimos
            <strong>{{ $window }}</strong> días.
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

    {{-- KPI cards globales --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section compact>
            <x-slot name="heading">Tickets resueltos</x-slot>
            <div class="text-3xl font-semibold">{{ $summary['resolved'] }}</div>
            <p class="text-xs text-zinc-500">Con SLA configurado</p>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">SLA quebrados</x-slot>
            <div class="text-3xl font-semibold {{ $summary['breached'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">
                {{ $summary['breached'] }}
            </div>
            <p class="text-xs text-zinc-500">
                {{ $summary['resolved'] > 0 ? round(($summary['breached'] / $summary['resolved']) * 100, 1).'%' : '0%' }}
                de los resueltos
            </p>
        </x-filament::section>

        <x-filament::section compact>
            <x-slot name="heading">Cumplimiento</x-slot>
            @if ($summary['compliance'] !== null)
                <div class="text-3xl font-semibold {{ $summary['compliance'] >= 90 ? 'text-emerald-600' : ($summary['compliance'] >= 70 ? 'text-amber-600' : 'text-rose-600') }}">
                    {{ $summary['compliance'] }}%
                </div>
                <p class="text-xs text-zinc-500">Resueltos sin breach</p>
            @else
                <div class="text-3xl font-semibold text-zinc-400">—</div>
                <p class="text-xs text-zinc-500">Sin tickets resueltos aún</p>
            @endif
        </x-filament::section>
    </div>

    {{-- ── Tickets en riesgo (mirar hacia adelante) ─────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Tickets en riesgo · vencen en las próximas 24h o ya vencidos</x-slot>
        <x-slot name="description">
            Tickets NO resueltos con SLA configurado. Permite intervenir antes de que el breach quede registrado.
        </x-slot>

        @if ($atRisk->isEmpty())
            <p class="text-sm text-emerald-600">🎉 Sin tickets en riesgo. Todos los SLA abiertos están holgados.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left font-medium">Ticket</th>
                            <th class="px-3 py-2 text-left font-medium">Departamento</th>
                            <th class="px-3 py-2 text-left font-medium">Asignado</th>
                            <th class="px-3 py-2 text-center font-medium">Prioridad</th>
                            <th class="px-3 py-2 text-right font-medium">Vence en</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($atRisk as $row)
                            @php($t = $row['ticket'])
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-3 py-2">
                                    <a href="{{ route('filament.admin.resources.tickets.view', ['record' => $t->id]) }}"
                                       class="font-mono text-xs text-primary-600 hover:underline">
                                        {{ $t->number }}
                                    </a>
                                    <div class="text-[11px] text-zinc-400">{{ Str::limit($t->subject, 50) }}</div>
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ $t->department?->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ $t->assignedTo?->name ?? '— Sin asignar —' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        @switch($t->priority->value)
                                            @case('critica') bg-red-100 text-red-800 @break
                                            @case('alta') bg-orange-100 text-orange-800 @break
                                            @case('media') bg-amber-100 text-amber-800 @break
                                            @default bg-zinc-100 text-zinc-700
                                        @endswitch
                                    ">
                                        {{ $t->priority->getLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    @if ($row['is_breached'])
                                        <span class="font-medium text-rose-600">
                                            Vencido hace {{ abs($row['hours_left']) }}h
                                        </span>
                                    @else
                                        <span class="font-medium {{ $row['hours_left'] <= 4 ? 'text-rose-600' : ($row['hours_left'] <= 12 ? 'text-amber-600' : 'text-zinc-600') }}">
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

    {{-- ── Matriz de cumplimiento por dept × prioridad ───────────── --}}
    <x-filament::section>
        <x-slot name="heading">Cumplimiento SLA por departamento ({{ $window }} días)</x-slot>
        <x-slot name="description">
            Cada celda muestra el porcentaje de tickets resueltos sin breach del cruce departamento × prioridad.
            Las celdas marcadas como "Sin datos" no tienen tickets cerrados con SLA en la ventana.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700">
                        <th class="px-3 py-2 text-left font-medium">Departamento</th>
                        @foreach ($priorities as $p)
                            <th class="px-3 py-2 text-center font-medium">{{ $p->getLabel() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report as $row)
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <td class="px-3 py-2 font-medium">{{ $row['department'] }}</td>
                            @foreach ($row['priorities'] as $p)
                                <td class="px-3 py-2 text-center">
                                    @if ($p['total'] > 0)
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium
                                            {{ $p['compliance'] >= 90 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                               ($p['compliance'] >= 70 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                                               'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                            {{ $p['compliance'] }}%
                                        </span>
                                        <div class="mt-0.5 text-[10px] text-zinc-400">
                                            {{ $p['total'] }} ticket{{ $p['total'] !== 1 ? 's' : '' }}
                                            @if ($p['breached'] > 0)
                                                · <span class="text-rose-500">{{ $p['breached'] }} breach</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400">Sin datos</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ── Últimas escalaciones ───────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Últimas escalaciones</x-slot>

        @if ($escalations->isEmpty())
            <p class="text-sm text-zinc-400">No hay escalaciones recientes.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-3 py-2 text-left font-medium">Ticket</th>
                            <th class="px-3 py-2 text-left font-medium">Tipo</th>
                            <th class="px-3 py-2 text-center font-medium">SLA (min)</th>
                            <th class="px-3 py-2 text-center font-medium">Transcurrido</th>
                            <th class="px-3 py-2 text-left font-medium">Notificado a</th>
                            <th class="px-3 py-2 text-left font-medium">Cuándo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($escalations as $esc)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="px-3 py-2">
                                    <span class="font-mono text-xs">{{ $esc->ticket?->number }}</span>
                                    <div class="text-[11px] text-zinc-400">{{ Str::limit($esc->ticket?->subject, 40) }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ str_contains($esc->type, 'breach') ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                           'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                        {{ str_replace('_', ' ', $esc->type) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center">{{ $esc->sla_minutes }}</td>
                                <td class="px-3 py-2 text-center font-mono">{{ $esc->elapsed_minutes }}</td>
                                <td class="px-3 py-2">{{ $esc->notifiedUser?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-400">{{ $esc->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
