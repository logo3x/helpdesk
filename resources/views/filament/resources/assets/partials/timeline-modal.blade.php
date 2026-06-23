@php
    $typeLabels = [
        'desktop'  => 'PC / Desktop',
        'laptop'   => 'Laptop',
        'printer'  => 'Impresora',
        'phone'    => 'Teléfono',
        'tablet'   => 'Tablet',
        'server'   => 'Servidor',
        'network'  => 'Red',
        'other'    => 'Otro',
    ];
    $statusColors = [
        'active'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'inactive' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        'retired'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'repair'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    ];
    $statusLabel = [
        'active'   => 'Activo',
        'inactive' => 'Inactivo',
        'retired'  => 'Dado de baja',
        'repair'   => 'En reparación',
    ];
    $maintenanceColors = [
        'vigente'    => 'bg-emerald-100 text-emerald-700',
        'por vencer' => 'bg-amber-100 text-amber-700',
        'vencido'    => 'bg-red-100 text-red-700',
    ];
    $eventColors = [
        'primary' => ['dot' => 'bg-indigo-500', 'ring' => 'ring-indigo-100 dark:ring-indigo-900/40', 'icon' => 'text-indigo-600 dark:text-indigo-300'],
        'warning' => ['dot' => 'bg-amber-500',  'ring' => 'ring-amber-100 dark:ring-amber-900/40',   'icon' => 'text-amber-600 dark:text-amber-300'],
        'info'    => ['dot' => 'bg-sky-500',     'ring' => 'ring-sky-100 dark:ring-sky-900/40',       'icon' => 'text-sky-600 dark:text-sky-300'],
        'gray'    => ['dot' => 'bg-gray-400',    'ring' => 'ring-gray-100 dark:ring-gray-700',        'icon' => 'text-gray-500 dark:text-gray-400'],
    ];
    $mStatus = $record->maintenance_status;
@endphp

{{-- ══════════════════════════════════════════════════════════════
     CABECERA DEL ACTIVO
══════════════════════════════════════════════════════════════ --}}
<div class="bg-gradient-to-r from-slate-800 to-slate-700 dark:from-slate-900 dark:to-slate-800 rounded-xl px-6 py-5 mb-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">

        {{-- Identidad --}}
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-xl bg-white/10 flex items-center justify-center shrink-0">
                <x-filament::icon icon="heroicon-o-computer-desktop" class="h-8 w-8 text-white/80" />
            </div>
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">
                    {{ $record->hostname ?: ('Activo #'.$record->id) }}
                </h2>
                <p class="text-sm text-slate-300 mt-0.5">
                    {{ $record->manufacturer }} {{ $record->model }}
                    @if ($record->serial_number)
                        <span class="text-slate-400"> · S/N {{ $record->serial_number }}</span>
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if ($record->asset_tag)
                        <span class="inline-flex items-center gap-1 rounded-md bg-white/10 px-2 py-0.5 text-xs font-mono text-white">
                            <x-filament::icon icon="heroicon-o-tag" class="h-3 w-3" />
                            {{ $record->asset_tag }}
                        </span>
                    @endif
                    <span @class(['inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium', $statusColors[$record->status] ?? 'bg-gray-100 text-gray-600'])>
                        {{ $statusLabel[$record->status] ?? ucfirst((string)$record->status) }}
                    </span>
                    @if ($record->type)
                        <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-0.5 text-xs text-slate-200">
                            {{ $typeLabels[$record->type] ?? ucfirst((string)$record->type) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Botón PDF --}}
        <div class="shrink-0">
            <a href="{{ route('assets.lifecycle.pdf', $record) }}"
               target="_blank"
               class="inline-flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 border border-white/20 px-4 py-2 text-sm font-medium text-white transition-colors">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                Descargar PDF
            </a>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     TARJETAS DE RESUMEN
══════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">

    {{-- Custodio --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Custodio</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
            {{ $record->user?->name ?? '— Sin asignar —' }}
        </p>
        @if ($record->user?->position)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $record->user->position }}</p>
        @endif
    </div>

    {{-- Departamento / Proyecto --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Proyecto</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
            {{ $record->project?->code ?? '—' }}
        </p>
        @if ($record->project?->name)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $record->project->name }}</p>
        @endif
        @if ($record->department)
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $record->department->name }}</p>
        @endif
    </div>

    {{-- Mantenimiento --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Mantenimiento</p>
        @if ($mStatus)
            <span @class(['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', $maintenanceColors[$mStatus] ?? 'bg-gray-100 text-gray-600'])>
                {{ ucfirst($mStatus) }}
            </span>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Próx.: {{ $record->next_maintenance_at?->translatedFormat('d M Y') ?? '—' }}
            </p>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Sin plan</p>
        @endif
    </div>

    {{-- Última ubicación / Campo --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 px-4 py-3">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Ubicación</p>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
            {{ $record->field ?: ($record->location_zone ?: '—') }}
        </p>
        @if ($record->last_scan_at)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Último scan: {{ $record->last_scan_at->translatedFormat('d M Y') }}
            </p>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     LÍNEA DE TIEMPO
══════════════════════════════════════════════════════════════ --}}
<div class="bg-white dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
    <div class="flex items-center gap-2 mb-5">
        <x-filament::icon icon="heroicon-o-queue-list" class="h-5 w-5 text-gray-400" />
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Línea de tiempo</h3>
        <span class="ml-auto text-xs text-gray-400">{{ count($events) }} evento(s)</span>
    </div>

    @if (empty($events))
        <div class="flex flex-col items-center justify-center py-10 text-gray-400">
            <x-filament::icon icon="heroicon-o-inbox" class="h-10 w-10 mb-2 opacity-40" />
            <p class="text-sm">Sin eventos registrados</p>
        </div>
    @else
        <ol class="relative ml-2 border-s-2 border-gray-100 dark:border-gray-700 space-y-0">
            @foreach ($events as $event)
                @php
                    $c = $eventColors[$event['color']] ?? $eventColors['gray'];
                @endphp
                <li class="relative pb-7 ms-7 last:pb-0">
                    {{-- Dot --}}
                    <span class="absolute -start-[1.05rem] flex h-7 w-7 items-center justify-center rounded-full bg-white dark:bg-gray-800 ring-4 {{ $c['ring'] }}">
                        <span class="h-3 w-3 rounded-full {{ $c['dot'] }}"></span>
                    </span>

                    <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 px-4 py-3">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$event['icon']" @class(['h-4 w-4 shrink-0', $c['icon']]) />
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</span>
                            </div>
                            <time class="text-xs text-gray-400 dark:text-gray-500 shrink-0 tabular-nums">
                                {{ $event['date']->translatedFormat('d M Y · H:i') }}
                            </time>
                        </div>

                        @if ($event['description'])
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                {{ $event['description'] }}
                            </p>
                        @endif

                        @if (! empty($event['meta']))
                            <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1">
                                @foreach ($event['meta'] as $label => $value)
                                    @if (! blank($value))
                                        <div class="flex items-baseline gap-1.5 text-xs">
                                            <dt class="text-gray-400 dark:text-gray-500 shrink-0">{{ $label }}:</dt>
                                            <dd class="text-gray-700 dark:text-gray-300 font-medium">{{ $value }}</dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
