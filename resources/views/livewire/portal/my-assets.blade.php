<div x-data="{}"
     x-init="
        document.querySelectorAll('.asset-card').forEach((el, i) => {
            el.style.animationDelay = (i * 60) + 'ms';
        });
     ">

    <style>
        @keyframes assetSlideIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .asset-card {
            animation: assetSlideIn .3s ease both;
            opacity: 0;
        }
    </style>

    {{-- Header --}}
    <div class="mb-6">
        <flux:heading size="xl">Mis activos</flux:heading>
        <flux:text size="sm" class="mt-0.5 text-zinc-400">
            Equipos de Confipetrol bajo tu custodia. Reporta cualquier daño o extravío al equipo de IT.
        </flux:text>
    </div>

    {{-- Filtro --}}
    <div class="mb-5">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Buscar por TAG, hostname, serial, fabricante o modelo..." />
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse ($assets as $asset)
            <div @class([
                'asset-card overflow-hidden rounded-xl border transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md',
                'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-950/30' => $asset->handovers->isNotEmpty(),
                'border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80' => $asset->handovers->isEmpty(),
            ])>
                <div class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            {{-- Icono tipo equipo --}}
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-950/50">
                                <flux:icon name="{{ match(strtolower($asset->type ?? '')) {
                                    'laptop', 'notebook' => 'computer-desktop',
                                    'server' => 'server',
                                    'printer', 'impresora' => 'printer',
                                    'phone', 'telefono', 'celular' => 'device-phone-mobile',
                                    default => 'cpu-chip',
                                } }}" class="size-5 text-sky-500" />
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-mono font-semibold uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $asset->type ?? 'EQUIPO' }}
                                    </span>
                                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $asset->asset_tag ?? '— sin TAG —' }}
                                    </span>
                                </div>

                                <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) ?: '—' }}
                                    @if ($asset->serial_number)
                                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                        <span class="font-mono text-xs text-zinc-400">S/N {{ $asset->serial_number }}</span>
                                    @endif
                                </div>

                                @if ($asset->hostname)
                                    <div class="mt-0.5 flex items-center gap-1 text-xs text-zinc-400">
                                        <flux:icon name="computer-desktop" class="size-3" />
                                        {{ $asset->hostname }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Estado + Proyecto --}}
                        <div class="flex flex-col items-end gap-1.5">
                            @php($status = $asset->status ?? 'active')
                            <flux:badge :color="$status === 'active' ? 'green' : 'amber'" size="sm">
                                {{ match($status) { 'active' => 'Activo', 'inactive' => 'Inactivo', 'retired' => 'De baja', 'repair' => 'En reparación', default => ucfirst($status) } }}
                            </flux:badge>

                            @if ($asset->project)
                                <div class="flex items-center gap-1 text-xs text-zinc-400">
                                    <flux:icon name="briefcase" class="size-3" />
                                    {{ $asset->project->code }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($asset->field || $asset->location_zone)
                        <div class="mt-3 flex items-center gap-1.5 border-t border-zinc-100 pt-3 text-xs text-zinc-400 dark:border-zinc-800">
                            <flux:icon name="map-pin" class="size-3 shrink-0" />
                            {{ trim(($asset->field ?? '').' · '.($asset->location_zone ?? ''), ' ·') }}
                        </div>
                    @endif
                </div>

                {{-- Actas de entrega pendientes --}}
                @foreach ($asset->handovers as $handover)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-900/30">
                        <div>
                            <div class="flex items-center gap-1.5 text-sm font-semibold text-amber-800 dark:text-amber-200">
                                <flux:icon name="document-text" class="size-4" />
                                Acta de entrega #{{ $handover->acta_number }} pendiente
                            </div>
                            <div class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                Entregada el {{ $handover->delivered_at->translatedFormat('d/m/Y') }}
                                · Condición: {{ ucfirst($handover->condition_at_delivery) }}
                            </div>
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
            <div class="rounded-xl border border-dashed border-zinc-200 p-10 text-center dark:border-zinc-700">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="cpu-chip" class="size-6 text-zinc-400" />
                </div>
                <flux:text size="sm" class="text-zinc-500">Aún no tienes activos asignados.</flux:text>
                <flux:text size="xs" class="mt-1 text-zinc-400">Contacta al equipo de IT si crees que hay un error.</flux:text>
            </div>
        @endforelse
    </div>

    @if ($assets->hasPages())
        <div class="mt-4">
            {{ $assets->links() }}
        </div>
    @endif
</div>
