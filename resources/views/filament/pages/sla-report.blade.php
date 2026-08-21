<x-filament-panels::page>

    <style>
        @keyframes slaFadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .sla-kpi     { animation: slaFadeUp .3s ease both; opacity: 0; }
        .sla-section { animation: slaFadeUp .35s ease .1s both; }

        /* KPI cards con estilos inline forzados — independientes del
           build de Tailwind del server, que a veces se queda viejo. */
        .sla-header { display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem; }
        @media (min-width:640px) { .sla-header { flex-direction:row; align-items:center; justify-content:space-between; } }
        .sla-header-select {
            border-radius:0.5rem; border:1px solid rgb(212 212 216);
            background:white; padding:0.375rem 0.75rem; font-size:0.875rem;
        }
        .sla-header-note { color:rgb(113 113 122); font-size:0.875rem; }

        .sla-kpi-grid { display:grid; gap:1rem; grid-template-columns:1fr; margin-bottom:1.5rem; }
        @media (min-width:640px) { .sla-kpi-grid { grid-template-columns:repeat(3,1fr); } }

        .sla-kpi {
            background:white;
            border:1px solid rgb(228 228 231);
            border-radius:0.75rem;
            padding:1.25rem;
            box-shadow:0 1px 2px rgba(0,0,0,0.04);
        }
        .dark .sla-kpi { background:rgb(24 24 27); border-color:rgb(63 63 70); }
        .sla-kpi.sla-kpi-danger { border-color:rgb(254 205 211); }
        .dark .sla-kpi.sla-kpi-danger { border-color:rgba(220,38,38,0.4); }

        .sla-kpi-icon-wrap {
            display:inline-flex; align-items:center; justify-content:center;
            width:2.25rem; height:2.25rem; border-radius:0.5rem;
            margin-bottom:0.5rem;
        }
        .sla-kpi-icon-info    { background:rgb(240 249 255); }
        .sla-kpi-icon-danger  { background:rgb(255 241 242); }
        .sla-kpi-icon-success { background:rgb(236 253 245); }
        .dark .sla-kpi-icon-info    { background:rgba(56,189,248,0.15); }
        .dark .sla-kpi-icon-danger  { background:rgba(239,68,68,0.15); }
        .dark .sla-kpi-icon-success { background:rgba(16,185,129,0.15); }

        .sla-kpi-value {
            font-size:1.5rem; font-weight:700; line-height:1.1;
            color:rgb(24 24 27); margin:0;
        }
        .dark .sla-kpi-value { color:rgb(244 244 245); }
        .sla-kpi-value.text-danger  { color:rgb(220 38 38); }
        .sla-kpi-value.text-warning { color:rgb(217 119 6); }
        .sla-kpi-value.text-success { color:rgb(22 163 74); }
        .sla-kpi-value.text-muted   { color:rgb(212 212 216); }

        .sla-kpi-label { margin-top:0.125rem; font-size:0.875rem; font-weight:500; color:rgb(113 113 122); }
        .sla-kpi-hint  { margin-top:0.25rem; font-size:0.75rem; color:rgb(161 161 170); }
    </style>

    {{-- ── Selector de ventana ──────────────────────────────────────── --}}
    <div class="sla-header">
        <p class="sla-header-note">
            Cumplimiento de SLA en los últimos <strong>{{ $window }}</strong> días.
        </p>
        <select wire:model.live="window" class="sla-header-select">
            <option value="7">Últimos 7 días</option>
            <option value="30">Últimos 30 días</option>
            <option value="90">Últimos 90 días</option>
            <option value="365">Último año</option>
        </select>
    </div>

    {{-- ── KPI cards globales ─────────────────────────────────────────── --}}
    <div class="sla-kpi-grid"
         x-data="{}"
         x-init="document.querySelectorAll('.sla-kpi').forEach((el,i)=>{ el.style.animationDelay=(i*60)+'ms'; })">

        <div class="sla-kpi">
            <div class="sla-kpi-icon-wrap sla-kpi-icon-info">
                <x-heroicon-o-ticket class="text-sky-500" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            <div class="sla-kpi-value">{{ $summary['resolved'] }}</div>
            <div class="sla-kpi-label">Tickets resueltos</div>
            <div class="sla-kpi-hint">Con SLA configurado</div>
        </div>

        <div class="sla-kpi {{ $summary['breached'] > 0 ? 'sla-kpi-danger' : '' }}">
            <div class="sla-kpi-icon-wrap {{ $summary['breached'] > 0 ? 'sla-kpi-icon-danger' : 'sla-kpi-icon-success' }}">
                <x-heroicon-o-exclamation-triangle class="{{ $summary['breached'] > 0 ? 'text-rose-500' : 'text-emerald-500' }}" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            <div class="sla-kpi-value {{ $summary['breached'] > 0 ? 'text-danger' : 'text-success' }}">
                {{ $summary['breached'] }}
            </div>
            <div class="sla-kpi-label">SLA quebrados</div>
            <div class="sla-kpi-hint">
                {{ $summary['resolved'] > 0 ? round(($summary['breached'] / $summary['resolved']) * 100, 1).'%' : '0%' }} de los resueltos
            </div>
        </div>

        <div class="sla-kpi">
            <div class="sla-kpi-icon-wrap sla-kpi-icon-success">
                <x-heroicon-o-check-badge class="text-emerald-500" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            @if ($summary['compliance'] !== null)
                <div class="sla-kpi-value {{ $summary['compliance'] >= 90 ? 'text-success' : ($summary['compliance'] >= 70 ? 'text-warning' : 'text-danger') }}">
                    {{ $summary['compliance'] }}%
                </div>
                <div class="sla-kpi-label">Cumplimiento</div>
                <div class="sla-kpi-hint">Resueltos sin breach</div>
            @else
                <div class="sla-kpi-value text-muted">—</div>
                <div class="sla-kpi-label">Cumplimiento</div>
                <div class="sla-kpi-hint">Sin tickets resueltos aún</div>
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
                <x-heroicon-o-check-circle style="width:1rem;height:1rem;flex-shrink:0;" />
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
