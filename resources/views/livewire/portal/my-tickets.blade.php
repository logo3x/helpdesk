<div x-data="{}"
     x-init="
        document.querySelectorAll('.ticket-item').forEach((el, i) => {
            el.style.animationDelay = (i * 50) + 'ms';
            el.style.opacity = '0';
        });
     ">

    <style>
        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .ticket-item {
            animation: slideInUp .3s ease both;
        }
        .ticket-item:hover .ticket-number-badge {
            transform: scale(1.05);
        }
    </style>

    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Mis tickets</flux:heading>
            <flux:text size="sm" class="mt-0.5 text-zinc-400">Seguimiento de todas tus solicitudes de soporte</flux:text>
        </div>
        <flux:button :href="route('portal.tickets.create')" variant="primary" icon="plus" wire:navigate>
            Crear ticket
        </flux:button>
    </div>

    {{-- Filtros --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por número o asunto..." />
        <flux:select wire:model.live="status" placeholder="Todos los estados">
            <flux:select.option value="">Todos los estados</flux:select.option>
            @foreach ($statusOptions as $opt)
                <flux:select.option :value="$opt->value">{{ $opt->getLabel() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Lista --}}
    <div class="space-y-2.5">
        @forelse ($tickets as $i => $ticket)
            <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate
               class="ticket-item group block overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-4 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:border-zinc-700/80 dark:bg-zinc-900/80 dark:hover:border-zinc-600">

                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        {{-- Badges de estado / prioridad --}}
                        <div class="mb-2 flex flex-wrap items-center gap-1.5">
                            <span class="ticket-number-badge inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-mono font-semibold text-zinc-600 transition-transform duration-150 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $ticket->number }}
                            </span>
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

                        {{-- Asunto --}}
                        <div class="text-sm font-semibold leading-snug text-zinc-900 group-hover:text-sky-600 transition-colors duration-150 dark:text-zinc-100 dark:group-hover:text-sky-400">
                            {{ $ticket->subject }}
                        </div>

                        {{-- Meta --}}
                        <div class="mt-1.5 flex flex-wrap items-center gap-1 text-xs text-zinc-400">
                            @if ($ticket->category)
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon name="tag" class="size-3" />
                                    {{ $ticket->category->name }}
                                </span>
                            @endif
                            @if ($ticket->assignee)
                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon name="user" class="size-3" />
                                    {{ $ticket->assignee->name }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Tiempo + flecha --}}
                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <flux:text size="xs" class="text-zinc-400">{{ $ticket->created_at->diffForHumans() }}</flux:text>
                        <flux:icon name="chevron-right" class="size-4 text-zinc-300 transition-transform duration-150 group-hover:translate-x-0.5 group-hover:text-sky-400" />
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 py-14 text-center dark:border-zinc-600"
                 style="animation: fadeIn .4s ease both">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="inbox" class="size-6 text-zinc-400" />
                </div>
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400">No tienes tickets aún</flux:heading>
                <flux:text size="sm" class="mt-1 text-zinc-400">Usa el botón "Crear ticket" para enviar una solicitud.</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
</div>
