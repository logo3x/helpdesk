<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl">Mis tickets</flux:heading>
        <flux:button :href="route('portal.tickets.create')" variant="primary" icon="plus" wire:navigate>
            Crear ticket
        </flux:button>
    </div>

    {{-- Filtros --}}
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por número o asunto..." />

        <flux:select wire:model.live="status" placeholder="Todos los estados">
            <flux:select.option value="">Todos los estados</flux:select.option>
            @foreach ($statusOptions as $opt)
                <flux:select.option :value="$opt->value">{{ $opt->getLabel() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Lista --}}
    <div class="space-y-3">
        @forelse ($tickets as $ticket)
            <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate
               class="block rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <flux:badge size="sm" color="zinc">{{ $ticket->number }}</flux:badge>
                            <flux:badge size="sm" :color="match($ticket->status->value) {
                                'nuevo' => 'sky',
                                'asignado' => 'blue',
                                'en_progreso' => 'amber',
                                'pendiente_cliente' => 'zinc',
                                'resuelto' => 'green',
                                'cerrado' => 'zinc',
                                'reabierto' => 'red',
                                default => 'zinc',
                            }">{{ $ticket->status->getLabel() }}</flux:badge>
                            <flux:badge size="sm" :color="match($ticket->priority->value) {
                                'planificada' => 'zinc',
                                'baja' => 'sky',
                                'media' => 'amber',
                                'alta' => 'red',
                                'critica' => 'red',
                                default => 'zinc',
                            }">{{ $ticket->priority->getLabel() }}</flux:badge>
                        </div>
                        <flux:heading size="sm" class="truncate">{{ $ticket->subject }}</flux:heading>
                        <flux:text size="sm" class="mt-1">
                            {{ $ticket->category?->name ?? 'Sin categoría' }}
                            @if ($ticket->assignee)
                                · Asignado a {{ $ticket->assignee->name }}
                            @endif
                        </flux:text>
                    </div>
                    <flux:text size="xs" class="shrink-0 text-zinc-400">
                        {{ $ticket->created_at->diffForHumans() }}
                    </flux:text>
                </div>
            </a>
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 py-12 text-center dark:border-zinc-600">
                <flux:icon name="inbox" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:heading size="sm">No tienes tickets</flux:heading>
                <flux:text class="mt-1">Crea uno nuevo con el botón de arriba.</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
</div>
