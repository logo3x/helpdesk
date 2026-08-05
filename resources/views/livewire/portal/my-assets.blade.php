<div x-data="{}"
     x-init="
        document.querySelectorAll('.asset-card').forEach((el, i) => {
            el.style.animationDelay = (i * 60) + 'ms';
        });
     ">

    <style>
        @keyframes assetSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .asset-card {
            animation: assetSlideIn .28s cubic-bezier(0.16,1,0.3,1) both;
            opacity: 0;
        }
        @media (prefers-reduced-motion: reduce) {
            .asset-card { animation: none !important; opacity: 1; }
        }
    </style>

    {{-- Header --}}
    <div class="mb-6 flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Mis activos</flux:heading>
            <flux:text size="sm" class="mt-0.5 text-zinc-400">
                Equipos bajo tu custodia. Reporta daños o extravíos al equipo de IT.
            </flux:text>
        </div>
        {{-- Contador visible --}}
        <span class="shrink-0 font-mono text-xs tracking-widest text-zinc-400">
            {{ $assets->total() }} {{ $assets->total() === 1 ? 'equipo' : 'equipos' }}
        </span>
    </div>

    {{-- Encuestas de mantenimiento pendientes --}}
    @if ($pendingSurveys->isNotEmpty())
        <div class="mb-5 space-y-2">
            @foreach ($pendingSurveys as $survey)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-950/30">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                            <flux:icon name="star" class="size-3.5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">Encuesta de mantenimiento pendiente</p>
                            <p class="text-xs text-amber-600 dark:text-amber-400">
                                {{ $survey->asset->hostname ?? $survey->asset->asset_tag ?? "Activo #{$survey->asset->id}" }}
                                @if ($survey->asset->manufacturer || $survey->asset->model)
                                    · {{ trim(($survey->asset->manufacturer ?? '').' '.($survey->asset->model ?? '')) }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <flux:button :href="route('portal.maintenance-survey', $survey->token)" wire:navigate
                                 size="sm" variant="primary" icon="star">
                        Calificar
                    </flux:button>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Filtro --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="TAG, hostname, serial, fabricante o modelo…" />
    </div>

    {{-- Lista --}}
    <div class="space-y-2">
        @forelse ($assets as $asset)
            @php
                $hasPendingHandover = $asset->handovers->isNotEmpty();
                $isAccepted = (bool) $asset->accepted_at;
                $assetIcon  = match(strtolower($asset->type ?? '')) {
                    'laptop', 'notebook' => 'computer-desktop',
                    'server'             => 'server',
                    'printer', 'impresora' => 'printer',
                    'phone', 'telefono', 'celular' => 'device-phone-mobile',
                    default              => 'cpu-chip',
                };
            @endphp

            <div @class([
                'asset-card overflow-hidden rounded-lg border',
                'border-amber-300/80 dark:border-amber-700/60' => $hasPendingHandover,
                'border-zinc-200 dark:border-zinc-700/70'      => ! $hasPendingHandover,
                'bg-white dark:bg-zinc-900'                    => ! $hasPendingHandover,
                'bg-amber-50/40 dark:bg-amber-950/20'          => $hasPendingHandover,
            ])>
                {{-- Franja superior de estado — solo cuando hay acta pendiente --}}
                @if ($hasPendingHandover)
                    <div class="flex items-center gap-2 border-b border-amber-200/80 bg-amber-100/60 px-4 py-2 dark:border-amber-800/40 dark:bg-amber-950/40">
                        <flux:icon name="document-text" class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Acta de entrega pendiente de confirmación</span>
                    </div>
                @endif

                <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">

                        {{-- Lado izquierdo: icono + identidad del equipo --}}
                        <div class="flex items-start gap-3">
                            <div @class([
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                'bg-emerald-50 dark:bg-emerald-950/40' => $isAccepted,
                                'bg-zinc-100 dark:bg-zinc-800'          => ! $isAccepted,
                            ])>
                                <flux:icon name="{{ $assetIcon }}" @class([
                                    'size-5',
                                    'text-emerald-500 dark:text-emerald-400' => $isAccepted,
                                    'text-zinc-400 dark:text-zinc-500'       => ! $isAccepted,
                                ]) />
                            </div>

                            <div class="min-w-0">
                                {{-- Tipo + TAG en la misma línea --}}
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-mono text-[10px] font-semibold uppercase tracking-widest text-zinc-400 dark:text-zinc-500">
                                        {{ $asset->type ?? 'EQUIPO' }}
                                    </span>
                                    @if ($asset->asset_tag)
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                            {{ $asset->asset_tag }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Hostname como título principal --}}
                                <p class="mt-0.5 text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $asset->hostname ?? trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) ?: '—' }}
                                </p>

                                {{-- Fabricante + modelo + serial --}}
                                <p class="mt-0.5 text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) ?: '—' }}
                                    @if ($asset->serial_number)
                                        <span class="ml-1 font-mono">S/N {{ $asset->serial_number }}</span>
                                    @endif
                                </p>

                                {{-- Ubicación inline --}}
                                @if ($asset->field || $asset->location_zone)
                                    <p class="mt-1 flex items-center gap-1 text-xs text-zinc-400 dark:text-zinc-500">
                                        <flux:icon name="map-pin" class="size-3 shrink-0" />
                                        {{ trim(($asset->field ?? '').' · '.($asset->location_zone ?? ''), ' ·') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        {{-- Lado derecho: estado + proyecto + acción --}}
                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @php($status = $asset->status ?? 'active')
                            <flux:badge :color="match($status) { 'active' => 'green', 'repair' => 'amber', 'retired' => 'zinc', default => 'zinc' }" size="sm">
                                {{ match($status) { 'active' => 'Activo', 'inactive' => 'Inactivo', 'retired' => 'De baja', 'repair' => 'En reparación', default => ucfirst($status) } }}
                            </flux:badge>

                            @if ($asset->project)
                                <span class="flex items-center gap-1 text-xs text-zinc-400">
                                    <flux:icon name="briefcase" class="size-3" />
                                    {{ $asset->project->code }}
                                </span>
                            @endif

                            {{-- Acción principal --}}
                            @if (! $isAccepted)
                                <flux:button wire:click="acceptAsset({{ $asset->id }})"
                                             wire:loading.attr="disabled"
                                             size="xs" variant="filled" icon="check">
                                    Aceptar activo
                                </flux:button>
                            @else
                                <div class="flex flex-col items-end gap-1">
                                    <span class="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                        <flux:icon name="check-circle" class="size-3.5" />
                                        Aceptado {{ $asset->accepted_at->translatedFormat('d/m/Y') }}
                                    </span>
                                    <flux:button :href="route('portal.assets.handover-pdf', $asset)" target="_blank"
                                                 size="xs" variant="ghost" icon="arrow-down-tray">
                                        Descargar acta
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Actas de entrega pendientes — franja inferior --}}
                @foreach ($asset->handovers as $handover)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-amber-200/80 bg-amber-50 px-4 py-3 dark:border-amber-800/40 dark:bg-amber-900/20">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                Acta #{{ $handover->acta_number }}
                            </p>
                            <p class="text-xs text-amber-600 dark:text-amber-400">
                                Entregada {{ $handover->delivered_at->translatedFormat('d M Y') }}
                                · {{ ucfirst($handover->condition_at_delivery) }}
                            </p>
                        </div>
                        <flux:button wire:click="confirmHandover({{ $handover->id }})"
                                     wire:loading.attr="disabled"
                                     variant="primary" size="sm" icon="check">
                            Confirmar recepción
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-200 p-12 text-center dark:border-zinc-700">
                <div class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="cpu-chip" class="size-5 text-zinc-400" />
                </div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Sin activos asignados</p>
                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Contacta al equipo de IT si crees que hay un error.</p>
            </div>
        @endforelse
    </div>

    @if ($assets->hasPages())
        <div class="mt-5">
            {{ $assets->links() }}
        </div>
    @endif
</div>
