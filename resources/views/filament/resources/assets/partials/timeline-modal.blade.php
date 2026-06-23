@php
    $typeIcons = [
        'desktop' => 'heroicon-o-computer-desktop',
        'laptop'  => 'heroicon-o-computer-desktop',
        'printer' => 'heroicon-o-printer',
        'phone'   => 'heroicon-o-device-phone-mobile',
        'tablet'  => 'heroicon-o-device-tablet',
        'server'  => 'heroicon-o-server',
        'network' => 'heroicon-o-wifi',
        'other'   => 'heroicon-o-cube',
    ];
    $typeLabels = [
        'desktop' => 'PC / Desktop', 'laptop' => 'Laptop', 'printer' => 'Impresora',
        'phone'   => 'Teléfono',     'tablet'  => 'Tablet', 'server'  => 'Servidor',
        'network' => 'Red',           'other'   => 'Otro',
    ];
    $statusColors = [
        'active'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 ring-1 ring-emerald-200 dark:ring-emerald-800',
        'inactive' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-gray-600',
        'retired'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 ring-1 ring-red-200 dark:ring-red-800',
        'repair'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 ring-1 ring-amber-200 dark:ring-amber-800',
    ];
    $statusLabel = [
        'active' => 'Activo', 'inactive' => 'Inactivo', 'retired' => 'Dado de baja', 'repair' => 'En reparación',
    ];
    $maintenanceColors = [
        'vigente'    => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        'por vencer' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        'vencido'    => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
    $eventStyles = [
        'primary' => [
            'dot'    => 'bg-indigo-500 shadow-indigo-200 dark:shadow-indigo-900',
            'ring'   => 'ring-indigo-100 dark:ring-indigo-900/50',
            'icon'   => 'text-indigo-600 dark:text-indigo-400',
            'border' => 'border-indigo-100 dark:border-indigo-900/50',
            'bg'     => 'bg-indigo-50/50 dark:bg-indigo-950/20',
            'line'   => 'bg-indigo-200 dark:bg-indigo-800',
        ],
        'warning' => [
            'dot'    => 'bg-amber-500 shadow-amber-200 dark:shadow-amber-900',
            'ring'   => 'ring-amber-100 dark:ring-amber-900/50',
            'icon'   => 'text-amber-600 dark:text-amber-400',
            'border' => 'border-amber-100 dark:border-amber-900/50',
            'bg'     => 'bg-amber-50/50 dark:bg-amber-950/20',
            'line'   => 'bg-amber-200 dark:bg-amber-800',
        ],
        'info' => [
            'dot'    => 'bg-sky-500 shadow-sky-200 dark:shadow-sky-900',
            'ring'   => 'ring-sky-100 dark:ring-sky-900/50',
            'icon'   => 'text-sky-600 dark:text-sky-400',
            'border' => 'border-sky-100 dark:border-sky-900/50',
            'bg'     => 'bg-sky-50/50 dark:bg-sky-950/20',
            'line'   => 'bg-sky-200 dark:bg-sky-800',
        ],
        'gray' => [
            'dot'    => 'bg-gray-400 shadow-gray-200 dark:shadow-gray-700',
            'ring'   => 'ring-gray-100 dark:ring-gray-700',
            'icon'   => 'text-gray-500 dark:text-gray-400',
            'border' => 'border-gray-100 dark:border-gray-700',
            'bg'     => 'bg-gray-50/50 dark:bg-gray-800/30',
            'line'   => 'bg-gray-200 dark:bg-gray-700',
        ],
    ];
    $mStatus = $record->maintenance_status;
    $typeIcon = $typeIcons[$record->type] ?? 'heroicon-o-cube';
@endphp

{{-- Animaciones CSS para stagger --}}
<style>
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeSlideRight {
        from { opacity: 0; transform: translateX(-10px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    @keyframes dotPop {
        0%   { transform: scale(0); opacity: 0; }
        60%  { transform: scale(1.25); }
        100% { transform: scale(1); opacity: 1; }
    }
    @keyframes lineDraw {
        from { transform: scaleY(0); transform-origin: top; }
        to   { transform: scaleY(1); transform-origin: top; }
    }
    @keyframes glowPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
        50%       { box-shadow: 0 0 0 6px rgba(99,102,241,0.15); }
    }
    .anim-header  { animation: fadeSlideUp .4s ease both; }
    .anim-card-1  { animation: fadeSlideUp .35s .05s ease both; }
    .anim-card-2  { animation: fadeSlideUp .35s .10s ease both; }
    .anim-card-3  { animation: fadeSlideUp .35s .15s ease both; }
    .anim-card-4  { animation: fadeSlideUp .35s .20s ease both; }
    .anim-timeline { animation: fadeSlideUp .4s .25s ease both; }
    .anim-event   { animation: fadeSlideRight .3s ease both; }
    .anim-dot     { animation: dotPop .4s cubic-bezier(.34,1.56,.64,1) both; }
    .timeline-connector { animation: lineDraw .5s .3s ease both; }
    .dot-glow-indigo { animation: glowPulse 2.5s ease-in-out infinite; }
</style>

{{-- ══════════════════════════════════════════════════════════════
     CABECERA
══════════════════════════════════════════════════════════════ --}}
<div class="anim-header relative overflow-hidden bg-gradient-to-br from-slate-800 via-slate-700 to-slate-800 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900 rounded-2xl px-6 py-5 mb-5 shadow-lg">

    {{-- Decoración de fondo --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
        <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/5 blur-2xl"></div>
        <div class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-indigo-500/10 blur-2xl"></div>
    </div>

    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            {{-- Ícono del equipo --}}
            <div class="h-14 w-14 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/10 flex items-center justify-center shrink-0 shadow-inner">
                <x-filament::icon :icon="$typeIcon" class="h-7 w-7 text-white/80" />
            </div>

            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">
                    {{ $record->hostname ?: ('Activo #'.$record->id) }}
                </h2>
                <p class="text-sm text-slate-300/90 mt-0.5">
                    {{ implode(' ', array_filter([$record->manufacturer, $record->model])) ?: 'Sin especificaciones' }}
                    @if ($record->serial_number)
                        <span class="text-slate-400"> · S/N {{ $record->serial_number }}</span>
                    @endif
                </p>

                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    @if ($record->asset_tag && $record->asset_tag !== 'N/A')
                        <span class="inline-flex items-center gap-1 rounded-lg bg-white/10 backdrop-blur-sm border border-white/10 px-2 py-0.5 text-xs font-mono text-white">
                            <x-filament::icon icon="heroicon-o-tag" class="h-3 w-3 text-white/60" />
                            {{ $record->asset_tag }}
                        </span>
                    @endif
                    <span @class(['inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold', $statusColors[$record->status] ?? 'bg-gray-100 text-gray-600'])>
                        {{ $statusLabel[$record->status] ?? ucfirst((string)$record->status) }}
                    </span>
                    @if ($record->type)
                        <span class="inline-flex items-center rounded-lg bg-white/10 px-2 py-0.5 text-xs text-slate-200">
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
               class="group inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 hover:border-white/30 px-4 py-2.5 text-sm font-medium text-white transition-all duration-200 shadow-sm hover:shadow-md">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4 transition-transform duration-200 group-hover:translate-y-0.5" />
                Descargar PDF
            </a>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     TARJETAS DE RESUMEN
══════════════════════════════════════════════════════════════ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">

    <div class="anim-card-1 group rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/60 px-4 py-3 shadow-sm hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-1.5">
            <x-filament::icon icon="heroicon-o-user-circle" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Custodio</p>
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
            {{ $record->user?->name ?? '— Sin asignar —' }}
        </p>
        @if ($record->user?->position)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $record->user->position }}</p>
        @endif
    </div>

    <div class="anim-card-2 group rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/60 px-4 py-3 shadow-sm hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-1.5">
            <x-filament::icon icon="heroicon-o-briefcase" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Proyecto</p>
        </div>
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

    <div class="anim-card-3 group rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/60 px-4 py-3 shadow-sm hover:shadow-md transition-all duration-200
        {{ $mStatus === 'vencido' ? 'hover:border-red-300 dark:hover:border-red-800' : ($mStatus === 'por vencer' ? 'hover:border-amber-300 dark:hover:border-amber-800' : 'hover:border-emerald-300 dark:hover:border-emerald-800') }}">
        <div class="flex items-center gap-1.5 mb-1.5">
            <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Mantenimiento</p>
        </div>
        @if ($mStatus)
            <span @class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', $maintenanceColors[$mStatus] ?? 'bg-gray-100 text-gray-600'])>
                {{ ucfirst($mStatus) }}
            </span>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Próx.: {{ $record->next_maintenance_at?->translatedFormat('d M Y') ?? '—' }}
            </p>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">Sin plan</p>
        @endif
    </div>

    <div class="anim-card-4 group rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/60 px-4 py-3 shadow-sm hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-800 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-1.5">
            <x-filament::icon icon="heroicon-o-map-pin" class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" />
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Ubicación</p>
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
            {{ $record->field ?: ($record->location_zone ?: '—') }}
        </p>
        @if ($record->last_scan_at)
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Scan: {{ $record->last_scan_at->translatedFormat('d M Y') }}
            </p>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     LÍNEA DE TIEMPO
══════════════════════════════════════════════════════════════ --}}
<div class="anim-timeline rounded-2xl border border-gray-200/80 dark:border-gray-700/80 bg-white dark:bg-gray-800/40 px-6 py-5 shadow-sm">

    {{-- Header de la sección --}}
    <div class="flex items-center gap-2.5 mb-6 pb-4 border-b border-gray-100 dark:border-gray-700/60">
        <div class="h-8 w-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center">
            <x-filament::icon icon="heroicon-o-queue-list" class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
        </div>
        <div>
            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Línea de tiempo</h3>
            <p class="text-xs text-gray-400 dark:text-gray-500">Historial completo de eventos del activo</p>
        </div>
        <span class="ml-auto inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-950/50 px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
            {{ count($events) }} eventos
        </span>
    </div>

    @if (empty($events))
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <div class="h-16 w-16 rounded-2xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center mb-3">
                <x-filament::icon icon="heroicon-o-inbox" class="h-8 w-8 opacity-40" />
            </div>
            <p class="text-sm font-medium">Sin eventos registrados</p>
            <p class="text-xs mt-1 opacity-60">Los eventos aparecerán aquí cuando se realicen cambios</p>
        </div>
    @else
        <ol class="relative space-y-0" x-data>
            @foreach ($events as $i => $event)
                @php
                    $s = $eventStyles[$event['color']] ?? $eventStyles['gray'];
                    $delay = min($i * 60, 600); // stagger máx 600ms
                @endphp
                <li class="anim-event relative flex gap-4 pb-6 last:pb-0 group/event"
                    style="animation-delay: {{ $delay }}ms">

                    {{-- Conector vertical --}}
                    @if (! $loop->last)
                        <div class="absolute left-[13px] top-7 bottom-0 w-0.5 {{ $s['line'] }} timeline-connector"
                             style="animation-delay: {{ $delay + 100 }}ms"></div>
                    @endif

                    {{-- Dot animado --}}
                    <div class="relative shrink-0 mt-1">
                        <div class="anim-dot h-7 w-7 rounded-full {{ $s['dot'] }} shadow-md ring-4 ring-white dark:ring-gray-800 flex items-center justify-center"
                             style="animation-delay: {{ $delay + 50 }}ms">
                            <x-filament::icon :icon="$event['icon']" class="h-3.5 w-3.5 text-white" />
                        </div>
                    </div>

                    {{-- Contenido del evento --}}
                    <div class="flex-1 min-w-0">
                        <div class="rounded-xl border {{ $s['border'] }} {{ $s['bg'] }} px-4 py-3 transition-all duration-200 group-hover/event:shadow-sm group-hover/event:scale-[1.002] origin-left">

                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
                                    {{ $event['title'] }}
                                </span>
                                <time class="shrink-0 inline-flex items-center gap-1 text-[11px] text-gray-400 dark:text-gray-500 tabular-nums bg-gray-100/80 dark:bg-gray-700/60 rounded-full px-2 py-0.5">
                                    <x-filament::icon icon="heroicon-o-clock" class="h-3 w-3" />
                                    {{ $event['date']->translatedFormat('d M Y · H:i') }}
                                </time>
                            </div>

                            @if ($event['description'])
                                <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                    {{ $event['description'] }}
                                </p>
                            @endif

                            @if (! empty($event['meta']))
                                <dl class="mt-2.5 flex flex-wrap gap-x-5 gap-y-1">
                                    @foreach ($event['meta'] as $label => $value)
                                        @if (! blank($value))
                                            <div class="flex items-baseline gap-1 text-xs">
                                                <dt class="text-gray-400 dark:text-gray-500">{{ $label }}:</dt>
                                                <dd class="text-gray-700 dark:text-gray-300 font-semibold">{{ $value }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
