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
        a.sla-kpi-link { display:block; text-decoration:none; transition:box-shadow .15s, transform .15s; }
        a.sla-kpi-link:hover { box-shadow:0 4px 12px rgba(0,0,0,0.08); transform:translateY(-1px); }
        .sla-kpi-cta { color:rgb(225 29 72); font-weight:600; }

        .sla-info {
            display:inline-block; vertical-align:-0.15rem; margin-left:0.2rem;
            color:rgb(161 161 170); cursor:help; flex-shrink:0;
        }
        .sla-info:hover { color:rgb(37 99 235); }

        .sla-help {
            margin-bottom:1rem; border:1px solid rgb(229 231 235); border-radius:0.5rem;
            background:white; font-size:0.85rem; color:rgb(63 63 70);
        }
        .dark .sla-help { background:rgb(24 24 27); border-color:rgb(63 63 70); color:rgb(212 212 216); }
        .sla-help summary { cursor:pointer; padding:0.6rem 1rem; font-weight:600; color:rgb(37 99 235); }
        .sla-help-body { padding:0 1rem 0.75rem; }
        .sla-help-body p { margin:0.4rem 0; }
        .sla-help-body ul { margin:0.4rem 0 0.4rem 1.25rem; list-style:disc; }
        .sla-help-body li { margin:0.2rem 0; }
    </style>

    {{-- ── Selector de rango ────────────────────────────────────────── --}}
    <style>
        .sla-range-bar {
            display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem;
            padding:0.75rem 1rem; margin-bottom:1rem;
            background:rgb(249 250 251); border:1px solid rgb(229 231 235);
            border-radius:0.5rem;
        }
        .dark .sla-range-bar { background:rgba(63,63,70,0.35); border-color:rgb(63 63 70); }
        .sla-range-note { flex:1; min-width:200px; font-size:0.85rem; color:rgb(75 85 99); margin:0; }
        .dark .sla-range-note { color:rgb(212 212 216); }
        .sla-preset-group { display:flex; flex-wrap:wrap; gap:0.25rem; }
        .sla-preset-btn {
            padding:0.35rem 0.75rem; font-size:0.75rem; font-weight:500;
            background:white; border:1px solid rgb(209 213 219); border-radius:0.375rem;
            color:rgb(55 65 81); cursor:pointer; transition:all 0.15s;
        }
        .dark .sla-preset-btn { background:rgb(24 24 27); border-color:rgb(63 63 70); color:rgb(212 212 216); }
        .sla-preset-btn:hover { background:rgb(243 244 246); }
        .dark .sla-preset-btn:hover { background:rgb(39 39 42); }
        .sla-preset-btn.active {
            background:rgb(59 130 246); color:white; border-color:rgb(37 99 235);
        }
        .sla-range-custom {
            display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem;
            padding-left:0.75rem; border-left:1px solid rgb(209 213 219);
        }
        .dark .sla-range-custom { border-left-color:rgb(63 63 70); }
        .sla-range-custom label { font-size:0.75rem; color:rgb(107 114 128); margin:0; }
        .sla-range-input {
            padding:0.3rem 0.5rem; font-size:0.8rem;
            border:1px solid rgb(209 213 219); border-radius:0.375rem;
            background:white; color:rgb(17 24 39);
        }
        .dark .sla-range-input { background:rgb(24 24 27); border-color:rgb(63 63 70); color:rgb(244 244 245); }
        .sla-clear-btn {
            padding:0.3rem 0.6rem; font-size:0.7rem; font-weight:500;
            background:transparent; border:none; color:rgb(107 114 128);
            cursor:pointer; text-decoration:underline;
        }
        .sla-clear-btn:hover { color:rgb(220 38 38); }
    </style>

    <div class="sla-range-bar">
        <p class="sla-range-note">
            @if ($isCustomRange ?? false)
                Cumplimiento del <strong>{{ $fromDate->translatedFormat('d M Y') }}</strong>
                al <strong>{{ $toDate->translatedFormat('d M Y') }}</strong>
                ({{ $window }} día{{ $window === 1 ? '' : 's' }}).
            @else
                Cumplimiento de SLA en los últimos <strong>{{ $window }}</strong> días.
            @endif
        </p>

        {{-- Presets rápidos --}}
        <div class="sla-preset-group">
            @foreach ([
                '7' => '7 días',
                '30' => '30 días',
                '90' => '90 días',
                '180' => '6 meses',
                '365' => '1 año',
            ] as $preset => $label)
                <button type="button"
                        wire:click="applyPreset('{{ $preset }}')"
                        class="sla-preset-btn {{ ! ($isCustomRange ?? false) && (string) $window === $preset ? 'active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Rango personalizado --}}
        <div class="sla-range-custom">
            <label for="dateFrom">Desde:</label>
            <input type="date" id="dateFrom" wire:model.live="dateFrom" class="sla-range-input" max="{{ now()->format('Y-m-d') }}">
            <label for="dateTo">Hasta:</label>
            <input type="date" id="dateTo" wire:model.live="dateTo" class="sla-range-input" max="{{ now()->format('Y-m-d') }}">
            @if ($isCustomRange ?? false)
                <button type="button" wire:click="clearCustomRange" class="sla-clear-btn" title="Limpiar rango">✕</button>
            @endif
        </div>
    </div>

    {{-- ── Cómo se mide el incumplimiento ──────────────────────────────── --}}
    <details class="sla-help">
        <summary>¿Qué significa que un ticket tenga el SLA incumplido y cómo se registra?</summary>
        <div class="sla-help-body">
            <p>
                Cada ticket toma, según su <strong>departamento y prioridad</strong>, una política SLA con dos límites:
                <strong>primera respuesta</strong> y <strong>resolución</strong>. Los límites se cuentan en
                <strong>horas hábiles</strong> (lunes a viernes, 8:00 a 18:00, hora de Bogotá). Las noches, fines de semana y el tiempo
                en estado <em>Pendiente cliente</em> no cuentan.
            </p>
            <p>
                Cada 5 minutos, en horario hábil, el sistema revisa los tickets abiertos y compara el tiempo hábil
                transcurrido con el límite:
            </p>
            <ul>
                <li><strong>Alerta 70%</strong> y <strong>Alerta 90%</strong>: avisos preventivos. Quedan en «Escalaciones» y el ticket sigue en cumplimiento.</li>
                <li><strong>Incumplido (100%)</strong>: se superó el límite. El ticket queda marcado con SLA incumplido y la marca no se borra al resolverlo.</li>
            </ul>
            <p>
                El <strong>% de cumplimiento</strong> se calcula sobre los tickets <strong>resueltos</strong> en el rango:
                (resueltos − incumplidos en resolución) ÷ resueltos.
            </p>
        </div>
    </details>

    {{-- ── KPI cards globales ─────────────────────────────────────────── --}}
    <div class="sla-kpi-grid"
         x-data="{}"
         x-init="document.querySelectorAll('.sla-kpi').forEach((el,i)=>{ el.style.animationDelay=(i*60)+'ms'; })">

        <div class="sla-kpi">
            <div class="sla-kpi-icon-wrap sla-kpi-icon-info">
                <x-heroicon-o-ticket class="text-sky-500" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            <div class="sla-kpi-value">{{ $summary['resolved'] }}</div>
            <div class="sla-kpi-label">Tickets resueltos <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tickets con política SLA resueltos dentro del rango seleccionado. Es la base del cálculo de cumplimiento." style="width:0.95rem;height:0.95rem;" /></div>
            <div class="sla-kpi-hint">Con SLA configurado</div>
        </div>

        <a href="#sla-incumplidos" class="sla-kpi sla-kpi-link {{ $summary['breached'] > 0 ? 'sla-kpi-danger' : '' }}">
            <div class="sla-kpi-icon-wrap {{ $summary['breached'] > 0 ? 'sla-kpi-icon-danger' : 'sla-kpi-icon-success' }}">
                <x-heroicon-o-exclamation-triangle class="{{ $summary['breached'] > 0 ? 'text-rose-500' : 'text-emerald-500' }}" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            <div class="sla-kpi-value {{ $summary['breached'] > 0 ? 'text-danger' : 'text-success' }}">
                {{ $summary['breached'] }}
            </div>
            <div class="sla-kpi-label">SLA incumplidos <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tickets resueltos en el rango que superaron el tiempo de resolución. Haz clic para ver el detalle." style="width:0.95rem;height:0.95rem;" /></div>
            <div class="sla-kpi-hint">
                {{ $summary['resolved'] > 0 ? round(($summary['breached'] / $summary['resolved']) * 100, 1).'%' : '0%' }} de los resueltos
                @if ($summary['breached'] > 0) · <span class="sla-kpi-cta">Ver detalle ↓</span> @endif
            </div>
        </a>

        <div class="sla-kpi">
            <div class="sla-kpi-icon-wrap sla-kpi-icon-success">
                <x-heroicon-o-check-badge class="text-emerald-500" style="width:1.25rem;height:1.25rem;flex-shrink:0;" />
            </div>
            @if ($summary['compliance'] !== null)
                <div class="sla-kpi-value {{ $summary['compliance'] >= 90 ? 'text-success' : ($summary['compliance'] >= 70 ? 'text-warning' : 'text-danger') }}">
                    {{ $summary['compliance'] }}%
                </div>
                <div class="sla-kpi-label">Cumplimiento <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="(Resueltos − incumplidos) ÷ resueltos. Verde desde 90%, ámbar desde 70%, rojo por debajo." style="width:0.95rem;height:0.95rem;" /></div>
                <div class="sla-kpi-hint">Resueltos dentro del SLA</div>
            @else
                <div class="sla-kpi-value text-muted">—</div>
                <div class="sla-kpi-label">Cumplimiento <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="(Resueltos − incumplidos) ÷ resueltos. Verde desde 90%, ámbar desde 70%, rojo por debajo." style="width:0.95rem;height:0.95rem;" /></div>
                <div class="sla-kpi-hint">Sin tickets resueltos aún</div>
            @endif
        </div>
    </div>

    {{-- ── Tickets en riesgo ─────────────────────────────────────────── --}}
    <x-filament::section class="sla-section">
        <x-slot name="heading">Tickets en riesgo · vencen en las próximas 24h o ya vencidos <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tickets abiertos cuyo plazo de resolución ya venció o vence en las próximas 24 horas. Úsalos para priorizar." style="width:0.95rem;height:0.95rem;" /></x-slot>
        <x-slot name="description">
            Tickets NO resueltos con SLA configurado. Permite intervenir antes de que el incumplimiento quede registrado.
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
                                    <a href="{{ route($ticketRoute, ['record' => $t->id]) }}"
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
        <x-slot name="heading">Cumplimiento SLA por departamento <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Porcentaje de tickets resueltos dentro del SLA por departamento y prioridad. Verde desde 90%, ámbar desde 70%, rojo por debajo." style="width:0.95rem;height:0.95rem;" />
            @if ($isCustomRange ?? false)
                ({{ $fromDate->translatedFormat('d M') }} — {{ $toDate->translatedFormat('d M Y') }})
            @else
                (últimos {{ $window }} días)
            @endif
        </x-slot>
        <x-slot name="description">
            Cada celda muestra el porcentaje de tickets resueltos dentro del SLA para ese departamento y prioridad. Haz clic en «incumplidos» para ver cuáles son.
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
                                                · <a href="#sla-incumplidos"
                                                     wire:click="filterBreaches({{ $row['department_id'] }}, '{{ $p['value'] }}')"
                                                     class="font-semibold text-rose-500 underline decoration-dotted hover:text-rose-700">{{ $p['breached'] }} incumplido{{ $p['breached'] !== 1 ? 's' : '' }}</a>
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

    {{-- ── Tickets con SLA incumplido ───────────────────────────────── --}}
    <div id="sla-incumplidos" style="scroll-margin-top:5rem;"></div>
    <x-filament::section class="sla-section">
        <x-slot name="heading">
            Tickets con SLA incumplido ({{ $breachedTickets->count() }}{{ $breachedTickets->count() >= 100 ? '+' : '' }})
            <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tickets resueltos en el rango cuyo tiempo de resolución superó el límite de su política SLA. Haz clic en el número para abrir el ticket." style="width:0.95rem;height:0.95rem;" />
        </x-slot>
        <x-slot name="description">
            Tickets resueltos en el rango que superaron el tiempo de resolución. Son los que cuenta el indicador «SLA incumplidos». Los tiempos están en horas hábiles.
        </x-slot>

        @if ($breachFilter)
            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                <span class="text-zinc-500">Filtrado por:</span>
                @if ($breachFilter['department'])
                    <span class="rounded-full bg-primary-50 px-2.5 py-0.5 font-semibold text-primary-700 dark:bg-primary-950/50 dark:text-primary-300">{{ $breachFilter['department'] }}</span>
                @endif
                @if ($breachFilter['priority'])
                    <span class="rounded-full bg-primary-50 px-2.5 py-0.5 font-semibold text-primary-700 dark:bg-primary-950/50 dark:text-primary-300">Prioridad {{ $breachFilter['priority'] }}</span>
                @endif
                <button type="button" wire:click="clearBreachFilter" class="text-zinc-500 underline hover:text-rose-600">Ver todos</button>
            </div>
        @endif

        @if ($breachedTickets->isEmpty())
            <div class="flex items-center gap-2 text-sm text-emerald-600">
                <x-heroicon-o-check-circle style="width:1rem;height:1rem;flex-shrink:0;" />
                No hay tickets resueltos con el SLA incumplido en este rango.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Departamento</th>
                            <th class="px-3 py-2.5 text-center font-semibold">Prioridad</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Atendido por <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Agente asignado actualmente al ticket." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-left font-semibold">Solicitante</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Límite SLA <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tiempo máximo de resolución de la política SLA según departamento y prioridad, en horas hábiles." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-right font-semibold">Tiempo real <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Horas hábiles desde la creación hasta la resolución, sin contar el tiempo en Pendiente cliente." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-right font-semibold">Exceso <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Cuánto tiempo hábil pasó después del límite." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-left font-semibold">Creado / Resuelto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($breachedTickets as $row)
                            @php($t = $row['ticket'])
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5">
                                    <a href="{{ route($ticketRoute, ['record' => $t->id]) }}"
                                       class="font-mono text-xs font-semibold text-primary-600 hover:underline">
                                        {{ $t->number }}
                                    </a>
                                    <div class="mt-0.5 text-xs text-zinc-400">{{ Str::limit($t->subject, 50) }}</div>
                                    @if ($t->first_response_breached)
                                        <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">También incumplió la primera respuesta</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $t->department?->name ?? '—' }}</td>
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
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $t->assignee?->name ?? '— Sin asignar —' }}</td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">{{ $t->requester?->name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs">{{ \App\Services\SlaService::formatMinutes($row['limit_minutes']) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs">
                                    {{ \App\Services\SlaService::formatMinutes($row['elapsed_minutes']) }}
                                    @if ($row['consumed_percent'] !== null)
                                        <div class="text-[10px] text-zinc-400">{{ $row['consumed_percent'] }}% del límite</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs font-semibold text-rose-600">
                                    {{ $row['overdue_minutes'] > 0 ? '+'.\App\Services\SlaService::formatMinutes($row['overdue_minutes']) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-zinc-500">
                                    {{ $t->created_at?->translatedFormat('d/m/Y H:i') }}
                                    <div>{{ $t->resolved_at?->translatedFormat('d/m/Y H:i') }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ── Últimas escalaciones ──────────────────────────────────────── --}}
    <x-filament::section class="sla-section">
        <x-slot name="heading">Últimas escalaciones ({{ $escalations->count() }}) <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Cada 5 minutos, en horario hábil, el sistema compara el tiempo transcurrido de los tickets abiertos con su límite y registra una alerta al 70%, otra al 90% y el incumplimiento al 100%." style="width:0.95rem;height:0.95rem;" /></x-slot>
        <x-slot name="description">
            Alertas registradas en el rango: avisos preventivos al 70% y 90% del tiempo, e incumplimientos al 100%. Se muestran las 50 más recientes.
        </x-slot>

        @if ($escalations->isEmpty())
            <p class="text-sm text-zinc-400">No hay escalaciones en este rango.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-xs uppercase tracking-wide text-zinc-400 dark:border-zinc-700">
                            <th class="px-3 py-2.5 text-left font-semibold">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Alerta <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Alerta 70% / 90%: aviso preventivo, el ticket aún cumple. Incumplido: se superó el 100% del límite." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-left font-semibold">Departamento</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Atendido por <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Agente asignado actualmente al ticket." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-center font-semibold">Estado actual <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Estado del ticket hoy, no al momento de la alerta." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-right font-semibold">Tiempo consumido <x-heroicon-o-information-circle class="sla-info" x-tooltip.raw="Tiempo hábil transcurrido al registrar la alerta frente al límite SLA." style="width:0.95rem;height:0.95rem;" /></th>
                            <th class="px-3 py-2.5 text-left font-semibold">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($escalations as $esc)
                            @php($et = $esc->ticket)
                            <tr class="border-b border-zinc-100 transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/40">
                                <td class="px-3 py-2.5">
                                    @if ($et)
                                        <a href="{{ route($ticketRoute, ['record' => $et->id]) }}"
                                           class="font-mono text-xs font-semibold text-primary-600 hover:underline">
                                            {{ $et->number }}
                                        </a>
                                        <div class="mt-0.5 text-xs text-zinc-400">{{ Str::limit($et->subject, 45) }}</div>
                                    @else
                                        <span class="text-xs text-zinc-400">Ticket eliminado</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        {{ $esc->isBreach()
                                            ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300'
                                            : 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300' }}">
                                        {{ $esc->typeLabel() }}
                                    </span>
                                    <div class="mt-0.5 text-xs text-zinc-500">{{ $esc->metricLabel() }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">
                                    {{ $et?->department?->name ?? '—' }}
                                    @if ($et?->priority)
                                        <div class="text-xs text-zinc-400">Prioridad {{ $et->priority->getLabel() }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-zinc-600 dark:text-zinc-300">
                                    {{ $et?->assignee?->name ?? '— Sin asignar —' }}
                                    @if ($esc->notifiedUser && $esc->notified_user_id !== $et?->assigned_to_id)
                                        <div class="text-xs text-zinc-400">Notificado: {{ $esc->notifiedUser->name }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if ($et?->status)
                                        <x-filament::badge :color="$et->status->getColor()">{{ $et->status->getLabel() }}</x-filament::badge>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono text-xs">
                                    {{ \App\Services\SlaService::formatMinutes($esc->elapsed_minutes) }} de {{ \App\Services\SlaService::formatMinutes($esc->sla_minutes) }}
                                    @if ($esc->consumedPercent() !== null)
                                        <div class="text-[10px] {{ $esc->isBreach() ? 'text-rose-500' : 'text-zinc-400' }}">{{ $esc->consumedPercent() }}%</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs text-zinc-500">
                                    {{ $esc->created_at->translatedFormat('d/m/Y H:i') }}
                                    <div class="text-zinc-400">{{ $esc->created_at->diffForHumans() }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
