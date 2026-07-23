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
            @php($survey = $ticket->satisfactionSurvey)
            <div @class([
                'ticket-item overflow-hidden rounded-xl border shadow-sm',
                'border-amber-300 dark:border-amber-700' => $survey && $survey->isPending(),
                'border-zinc-200/80 dark:border-zinc-700/80' => ! ($survey && $survey->isPending()),
            ])>
                <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate
                   class="group block bg-white p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md dark:bg-zinc-900/80 dark:hover:border-zinc-600">

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
                                @if ($survey)
                                    @if ($survey->isPending())
                                        <flux:badge size="sm" color="amber" icon="star">Encuesta pendiente</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="green" icon="star">
                                            Calificado {{ str_repeat('★', $survey->rating) }}
                                        </flux:badge>
                                    @endif
                                @endif
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

                {{-- Banner encuesta pendiente --}}
                @if ($survey && $survey->isPending())
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-amber-200 bg-amber-50 px-4 py-2.5 dark:border-amber-800/60 dark:bg-amber-950/30">
                        <div class="flex items-center gap-1.5 text-sm text-amber-700 dark:text-amber-300">
                            <flux:icon name="star" class="size-4 shrink-0 text-amber-500" />
                            ¿Cómo fue tu experiencia? Tu opinión nos ayuda a mejorar.
                        </div>
                        <flux:button :href="route('portal.survey', $survey->token)" wire:navigate
                                     size="sm" variant="primary" icon="star">
                            Calificar atención
                        </flux:button>
                    </div>
                @elseif ($survey && ! $survey->isPending())
                    {{-- Resultado de encuesta ya respondida --}}
                    <div
                        x-data="{
                            open: false,
                            rating: {{ $survey->rating ?? 0 }},
                            comment: @js($survey->comment ?? ''),
                            respondedAt: @js($survey->responded_at?->translatedFormat('d/m/Y H:i') ?? '')
                        }"
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-green-200 bg-green-50 px-4 py-2.5 dark:border-green-800/60 dark:bg-green-950/30"
                    >
                        <div class="flex items-center gap-1.5 text-sm text-green-700 dark:text-green-300">
                            <flux:icon name="star" class="size-4 shrink-0 text-green-500" />
                            Calificado con {{ str_repeat('★', $survey->rating) }} el {{ $survey->responded_at?->translatedFormat('d/m/Y') }}
                        </div>
                        <button
                            type="button"
                            @click="open = true"
                            class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-300 hover:bg-green-100 dark:text-green-300 dark:ring-green-700 dark:hover:bg-green-900/40"
                        >
                            Ver resultado
                        </button>

                        {{-- Modal resultado --}}
                        <div
                            x-show="open"
                            x-cloak
                            @keydown.escape.window="open = false"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        >
                            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>
                            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                                <button @click="open = false" class="absolute right-4 top-4 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                                    <flux:icon name="x-mark" class="size-5" />
                                </button>
                                <div class="mb-4 flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                        <flux:icon name="star" class="size-5 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Resultado de encuesta</h3>
                                        <p class="text-xs text-zinc-500" x-text="respondedAt"></p>
                                    </div>
                                </div>
                                <div class="mb-3 flex gap-1">
                                    <template x-for="i in 5" :key="i">
                                        <flux:icon name="star" :class="i <= rating ? 'text-amber-400 size-7' : 'text-zinc-300 size-7 dark:text-zinc-600'" />
                                    </template>
                                </div>
                                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    <span x-text="rating + '/5 — ' + ({1:'Muy insatisfecho',2:'Insatisfecho',3:'Regular',4:'Satisfecho',5:'Muy satisfecho'}[rating] || '')"></span>
                                </p>
                                <template x-if="comment">
                                    <div class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-400">Comentario</p>
                                        <p x-text="comment"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
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
