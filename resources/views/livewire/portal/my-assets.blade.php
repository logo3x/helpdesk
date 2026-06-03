<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl">Mis activos</flux:heading>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">
            Equipos de Confipetrol bajo tu custodia. Reporta cualquier daño o extravío al equipo de IT.
        </p>
    </div>

    {{-- Filtro de búsqueda --}}
    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="Buscar por TAG, hostname, serial, fabricante o modelo..." />
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse ($assets as $asset)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:badge color="zinc" size="sm">{{ strtoupper($asset->type ?? 'EQUIPO') }}</flux:badge>
                            <span class="font-semibold">{{ $asset->asset_tag ?? '— sin TAG —' }}</span>
                        </div>

                        <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ $asset->manufacturer }} {{ $asset->model }}
                            @if ($asset->serial_number)
                                <span class="text-zinc-400">·</span>
                                <span class="font-mono text-xs">S/N {{ $asset->serial_number }}</span>
                            @endif
                        </div>

                        @if ($asset->hostname)
                            <div class="mt-1 text-xs text-zinc-500">Hostname: {{ $asset->hostname }}</div>
                        @endif
                    </div>

                    <div class="text-right">
                        @php($status = $asset->status ?? 'active')
                        <flux:badge :color="$status === 'active' ? 'green' : 'amber'" size="sm">
                            {{ ucfirst($status) }}
                        </flux:badge>

                        @if ($asset->project)
                            <div class="mt-1 text-xs text-zinc-500">
                                Proyecto: {{ $asset->project->code }}
                            </div>
                        @endif
                    </div>
                </div>

                @if ($asset->field || $asset->location_zone)
                    <div class="mt-3 border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-800">
                        Ubicación: {{ trim(($asset->field ?? '').' · '.($asset->location_zone ?? ''), ' ·') }}
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-200 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                Aún no tienes activos asignados.
            </div>
        @endforelse
    </div>

    @if ($assets->hasPages())
        <div class="mt-4">
            {{ $assets->links() }}
        </div>
    @endif
</div>
