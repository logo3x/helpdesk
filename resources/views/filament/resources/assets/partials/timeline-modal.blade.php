@php
    $typeIcons = [
        'desktop'    => 'heroicon-o-computer-desktop',
        'laptop'     => 'heroicon-o-computer-desktop',
        'notebook'   => 'heroicon-o-computer-desktop',
        'printer'    => 'heroicon-o-printer',
        'phone'      => 'heroicon-o-device-phone-mobile',
        'tablet'     => 'heroicon-o-device-tablet',
        'server'     => 'heroicon-o-server',
        'network'    => 'heroicon-o-wifi',
        'other'      => 'heroicon-o-cube',
        'workstation'=> 'heroicon-o-computer-desktop',
    ];
    $typeLabels = [
        'desktop'    => 'PC / Desktop', 'laptop' => 'Laptop', 'notebook'   => 'Notebook',
        'printer'    => 'Impresora',    'phone'  => 'Teléfono','tablet'    => 'Tablet',
        'server'     => 'Servidor',     'network'=> 'Red',     'other'     => 'Otro',
        'workstation'=> 'Workstation',  'all_in_one' => 'All-in-One',
    ];
    $mStatus  = $record->maintenance_status;
    $typeIcon = $typeIcons[$record->type] ?? 'heroicon-o-cube';

    // Colores de estado — clases completas para que Tailwind las incluya en el build
    $statusBadge = match($record->status) {
        'active'    => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
        'fair'      => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200',
        'in_repair' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
        'retired'   => 'bg-red-100 text-red-700 ring-1 ring-red-200',
        default     => 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
    };
    $statusLabel = match($record->status) {
        'active'    => 'Activo',
        'fair'      => 'Regular',
        'in_repair' => 'En reparación',
        'retired'   => 'Dado de baja',
        default     => ucfirst((string) $record->status),
    };

    // Colores de mantenimiento
    $mBadge = match($mStatus) {
        'vigente'    => 'bg-emerald-100 text-emerald-700',
        'por vencer' => 'bg-amber-100 text-amber-700',
        'vencido'    => 'bg-red-100 text-red-700',
        default      => 'bg-gray-100 text-gray-500',
    };
@endphp

{{-- Animaciones --}}
<style>
    @keyframes tlFadeUp {
        from { opacity:0; transform:translateY(10px); }
        to   { opacity:1; transform:translateY(0); }
    }
    @keyframes tlFadeRight {
        from { opacity:0; transform:translateX(-8px); }
        to   { opacity:1; transform:translateX(0); }
    }
    @keyframes tlDotPop {
        0%   { transform:scale(0); opacity:0; }
        65%  { transform:scale(1.2); }
        100% { transform:scale(1); opacity:1; }
    }
    .tl-header  { animation: tlFadeUp .35s ease both; }
    .tl-cards   { animation: tlFadeUp .35s .08s ease both; }
    .tl-section { animation: tlFadeUp .35s .16s ease both; }
    .tl-event   { animation: tlFadeRight .3s ease both; }
    .tl-dot     { animation: tlDotPop .4s cubic-bezier(.34,1.56,.64,1) both; }
</style>

{{-- ═══════════════════════════════════════════════
     HEADER: gradiente oscuro con info del equipo
═══════════════════════════════════════════════ --}}
<div class="tl-header relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-slate-700 to-slate-800 px-6 py-5 mb-4 shadow-lg">

    {{-- Decoración de fondo --}}
    <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-2xl">
        <div class="absolute -top-8 -right-8 h-36 w-36 rounded-full bg-white/5 blur-2xl"></div>
        <div class="absolute -bottom-6 -left-6 h-28 w-28 rounded-full bg-indigo-500/10 blur-2xl"></div>
    </div>

    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

        {{-- Ícono + datos principales --}}
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center shrink-0">
                <x-filament::icon :icon="$typeIcon" class="h-7 w-7 text-white/80" />
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-0.5">
                    Hoja de vida · {{ $typeLabels[$record->type] ?? $record->type }}
                </p>
                <h2 class="text-xl font-bold text-white leading-tight">
                    {{ $record->hostname ?: ($record->asset_tag ?: 'Activo #'.$record->id) }}
                </h2>
                <p class="text-sm text-slate-300/80 mt-0.5">
                    {{ implode(' ', array_filter([$record->manufacturer, $record->model])) ?: 'Sin especificaciones' }}
                    @if($record->serial_number)
                        <span class="text-slate-400"> · S/N {{ $record->serial_number }}</span>
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                    @if($record->asset_tag)
                        <span class="inline-flex items-center gap-1 rounded-md bg-white/10 border border-white/10 px-2 py-0.5 text-xs font-mono text-white/90">
                            <x-filament::icon icon="heroicon-o-tag" class="h-3 w-3 text-white/50" />
                            {{ $record->asset_tag }}
                        </span>
                    @endif
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold {{ $statusBadge }}">
                        {{ $statusLabel }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Botón PDF --}}
        <a href="{{ route('assets.lifecycle.pdf', $record) }}" target="_blank"
           class="group shrink-0 inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/15 hover:border-white/30 px-4 py-2.5 text-sm font-medium text-white transition-all duration-200">
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-4 w-4 transition-transform duration-200 group-hover:translate-y-0.5" />
            Descargar PDF
        </a>
    </div>
</div>

{{-- ═══════════════════════════════════════════════
     TARJETAS DE RESUMEN (4 columnas)
═══════════════════════════════════════════════ --}}
<div class="tl-cards grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">

    {{-- Custodio --}}
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
        <div class="flex items-center gap-1.5 mb-2">
            <div class="h-6 w-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-user" class="h-3.5 w-3.5 text-indigo-500" />
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Custodio</p>
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug truncate">
            {{ $record->user?->name ?? $record->custodian_name ?? '— Sin asignar —' }}
        </p>
        @if($record->custodian_name && $record->user)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $record->custodian_name }}</p>
        @elseif($record->user?->position)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $record->user->position }}</p>
        @elseif($record->department)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $record->department->name }}</p>
        @endif
    </div>

    {{-- Proyecto --}}
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
        <div class="flex items-center gap-1.5 mb-2">
            <div class="h-6 w-6 rounded-lg bg-violet-50 dark:bg-violet-950/50 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-briefcase" class="h-3.5 w-3.5 text-violet-500" />
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Proyecto</p>
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug truncate">
            {{ $record->project ? $record->project->code.' · '.$record->project->name : '—' }}
        </p>
        @if($record->department)
            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $record->department->name }}</p>
        @endif
    </div>

    {{-- Mantenimiento --}}
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
        <div class="flex items-center gap-1.5 mb-2">
            <div class="h-6 w-6 rounded-lg bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" class="h-3.5 w-3.5 text-amber-500" />
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Mantenimiento</p>
        </div>
        @if($mStatus)
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $mBadge }}">
                {{ ucfirst($mStatus) }}
            </span>
            <p class="text-xs text-gray-400 mt-1">
                Próx.: {{ $record->next_maintenance_at?->translatedFormat('d M Y') ?? '—' }}
            </p>
        @else
            <p class="text-sm text-gray-400">Sin plan</p>
        @endif
    </div>

    {{-- Ubicación / Scan --}}
    <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
        <div class="flex items-center gap-1.5 mb-2">
            <div class="h-6 w-6 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-map-pin" class="h-3.5 w-3.5 text-emerald-500" />
            </div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Ubicación</p>
        </div>
        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug truncate">
            {{ $record->field ?: ($record->location_zone ?: ($record->management_area ?: '—')) }}
        </p>
        @if($record->last_scan_at)
            <p class="text-xs text-gray-400 mt-0.5">
                Scan {{ $record->last_scan_at->diffForHumans() }}
            </p>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════
     HARDWARE (si hay datos)
═══════════════════════════════════════════════ --}}
@if($record->os_name || $record->cpu_model || $record->ram_mb || $record->ip_address)
<div class="tl-section mb-4 rounded-xl border border-gray-200 bg-gray-50/60 dark:border-gray-700 dark:bg-gray-800/30 px-4 py-3">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Especificaciones técnicas</p>
    <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 sm:grid-cols-4">
        @if($record->os_name)
        <div>
            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Sistema operativo</p>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 leading-snug">
                {{ $record->os_name }}
                @if($record->os_version) <span class="text-xs text-gray-400">{{ $record->os_version }}</span> @endif
            </p>
        </div>
        @endif
        @if($record->cpu_model)
        <div>
            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Procesador</p>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 leading-snug truncate" title="{{ $record->cpu_model }}">
                {{ $record->cpu_model }}
                @if($record->cpu_cores) <span class="text-xs text-gray-400">({{ $record->cpu_cores }} cores)</span> @endif
            </p>
        </div>
        @endif
        @if($record->ram_mb)
        <div>
            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Memoria RAM</p>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ round($record->ram_mb / 1024) }} GB
                @if($record->disk_total_gb) · {{ $record->disk_total_gb }} GB disco @endif
            </p>
        </div>
        @endif
        @if($record->ip_address)
        <div>
            <p class="text-[10px] text-gray-400 uppercase tracking-wide">Red</p>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 font-mono">{{ $record->ip_address }}</p>
            @if($record->mac_address)
                <p class="text-xs text-gray-400 font-mono">{{ $record->mac_address }}</p>
            @endif
        </div>
        @endif
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════
     LÍNEA DE TIEMPO
═══════════════════════════════════════════════ --}}
<div class="tl-section rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800/40 px-5 py-4 shadow-sm">

    <div class="flex items-center justify-between gap-2 mb-5 pb-3 border-b border-gray-100 dark:border-gray-700/60">
        <div class="flex items-center gap-2.5">
            <div class="h-7 w-7 rounded-lg bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center">
                <x-filament::icon icon="heroicon-o-queue-list" class="h-4 w-4 text-indigo-500" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Línea de tiempo</h3>
                <p class="text-xs text-gray-400">Historial completo de eventos</p>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-950/50 px-2.5 py-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
            {{ count($events) }} eventos
        </span>
    </div>

    @if(empty($events))
        <div class="flex flex-col items-center justify-center py-10 text-gray-400">
            <div class="h-14 w-14 rounded-xl bg-gray-50 dark:bg-gray-800 flex items-center justify-center mb-3">
                <x-filament::icon icon="heroicon-o-inbox" class="h-7 w-7 opacity-40" />
            </div>
            <p class="text-sm font-medium">Sin eventos registrados</p>
            <p class="text-xs mt-1 opacity-60">Los cambios aparecerán aquí</p>
        </div>
    @else
        <ol class="relative space-y-0">
            @foreach($events as $i => $event)
                @php
                    // Paleta por color — clases completas (Tailwind no purga las dinámicas)
                    $dotCls   = match($event['color']) {
                        'primary' => 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-900/50',
                        'warning' => 'bg-amber-500 ring-amber-100 dark:ring-amber-900/50',
                        'info'    => 'bg-sky-500 ring-sky-100 dark:ring-sky-900/50',
                        default   => 'bg-gray-400 ring-gray-100 dark:ring-gray-700',
                    };
                    $lineCls  = match($event['color']) {
                        'primary' => 'bg-indigo-200 dark:bg-indigo-800',
                        'warning' => 'bg-amber-200 dark:bg-amber-800',
                        'info'    => 'bg-sky-200 dark:bg-sky-800',
                        default   => 'bg-gray-200 dark:bg-gray-700',
                    };
                    $cardCls  = match($event['color']) {
                        'primary' => 'border-indigo-100 bg-indigo-50/50 dark:border-indigo-900/50 dark:bg-indigo-950/20',
                        'warning' => 'border-amber-100 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20',
                        'info'    => 'border-sky-100 bg-sky-50/50 dark:border-sky-900/50 dark:bg-sky-950/20',
                        default   => 'border-gray-100 bg-gray-50/50 dark:border-gray-700 dark:bg-gray-800/30',
                    };
                    $iconCls  = match($event['color']) {
                        'primary' => 'text-indigo-600 dark:text-indigo-400',
                        'warning' => 'text-amber-600 dark:text-amber-400',
                        'info'    => 'text-sky-600 dark:text-sky-400',
                        default   => 'text-gray-500 dark:text-gray-400',
                    };
                    $delay = min($i * 55, 550);
                @endphp

                <li class="tl-event relative flex gap-3 pb-5 last:pb-0"
                    style="animation-delay:{{ $delay }}ms">

                    {{-- Línea vertical --}}
                    @unless($loop->last)
                        <div class="absolute left-[13px] top-7 bottom-0 w-0.5 {{ $lineCls }}"></div>
                    @endunless

                    {{-- Dot --}}
                    <div class="tl-dot shrink-0 mt-0.5 h-7 w-7 rounded-full {{ $dotCls }} shadow ring-4 flex items-center justify-center"
                         style="animation-delay:{{ $delay + 40 }}ms">
                        <x-filament::icon :icon="$event['icon']" class="h-3.5 w-3.5 text-white" />
                    </div>

                    {{-- Card del evento --}}
                    <div class="flex-1 min-w-0 rounded-xl border {{ $cardCls }} px-4 py-3">

                        <div class="flex items-start justify-between gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-snug">
                                {{ $event['title'] }}
                            </span>
                            <time class="shrink-0 inline-flex items-center gap-1 text-[11px] text-gray-400 tabular-nums bg-white/70 dark:bg-gray-700/50 rounded-full px-2 py-0.5 border border-gray-200/60 dark:border-gray-600/40">
                                <x-filament::icon icon="heroicon-o-clock" class="h-3 w-3" />
                                {{ $event['date']->translatedFormat('d M Y · H:i') }}
                            </time>
                        </div>

                        @if($event['description'])
                            <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                {{ $event['description'] }}
                            </p>
                        @endif

                        @if(!empty($event['meta']))
                            <dl class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach($event['meta'] as $label => $value)
                                    @if(!blank($value))
                                        @php
                                            // Truncar User-Agent largo
                                            $display = (str_contains(strtolower($label), 'agent') || str_contains(strtolower($label), 'user'))
                                                ? Str::limit($value, 60)
                                                : $value;
                                        @endphp
                                        <div class="flex items-baseline gap-1 text-xs">
                                            <dt class="text-gray-400 shrink-0">{{ $label }}:</dt>
                                            <dd class="text-gray-700 dark:text-gray-300 font-medium truncate max-w-xs" title="{{ $value }}">{{ $display }}</dd>
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
