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
                    @php($dims = \App\Models\SatisfactionSurvey::DIMENSIONS)
                    @php($avgRating = $survey->averageRating() ?? $survey->rating ?? 0)
                    @php($modalId = 'survey-modal-'.$survey->id)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-green-200 bg-green-50 px-4 py-2.5 dark:border-green-800/60 dark:bg-green-950/30">
                        <div class="flex items-center gap-1.5 text-sm text-green-700 dark:text-green-300">
                            <span class="text-amber-400 text-base leading-none">{{ str_repeat('★', (int) round($avgRating)) }}{{ str_repeat('☆', 5 - (int) round($avgRating)) }}</span>
                            {{ number_format($avgRating, 1) }}/5 — {{ $survey->responded_at?->translatedFormat('d/m/Y') }}
                        </div>
                        <button
                            type="button"
                            @click="$dispatch('open-survey-modal', { id: '{{ $modalId }}' })"
                            class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-green-700 ring-1 ring-green-300 hover:bg-green-100 dark:text-green-300 dark:ring-green-700 dark:hover:bg-green-900/40"
                        >
                            Ver detalle
                        </button>

                        {{-- Modal con teleport al body para evitar z-index y overflow issues --}}
                        <template x-teleport="body">
                            <div
                                x-data="{ open: false, id: '{{ $modalId }}' }"
                                x-show="open"
                                x-cloak
                                @open-survey-modal.window="if ($event.detail.id === id) open = true"
                                @keydown.escape.window="open = false"
                                class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
                                style="display: none"
                            >
                                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="open = false"></div>
                                <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-zinc-900 max-h-[85vh] flex flex-col">
                                    {{-- Header del modal --}}
                                    <div class="flex items-center gap-3 border-b border-zinc-100 dark:border-zinc-800 px-5 py-4 shrink-0">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                                            <span class="text-lg text-amber-500">★</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Resultado de encuesta</h3>
                                            <p class="text-xs text-zinc-500">{{ $survey->responded_at?->translatedFormat('d \d\e M\. Y, H:i') }}</p>
                                        </div>
                                        <button @click="open = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors">
                                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>

                                    {{-- Cuerpo scrolleable --}}
                                    <div class="overflow-y-auto p-5 space-y-3">
                                        @foreach($dims as $field => $label)
                                            @php($val = $survey->{$field} ?? 0)
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-xs text-zinc-600 dark:text-zinc-400 leading-tight flex-1">{{ $label }}</span>
                                                <div class="flex items-center gap-0.5 shrink-0">
                                                    @foreach([1,2,3,4,5] as $n)
                                                        <span class="text-lg leading-none {{ $val >= $n ? 'text-amber-400' : 'text-zinc-200 dark:text-zinc-700' }}">★</span>
                                                    @endforeach
                                                    <span class="ml-1.5 text-xs font-semibold text-zinc-500 dark:text-zinc-400 w-6 text-right">{{ $val }}/5</span>
                                                </div>
                                            </div>
                                        @endforeach

                                        {{-- Promedio --}}
                                        <div class="mt-3 rounded-xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-950/30 dark:to-orange-950/20 px-4 py-3 flex items-center justify-between border border-amber-200/60 dark:border-amber-800/40">
                                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide">Promedio general</span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-amber-400 text-lg leading-none">{{ str_repeat('★', (int) round($avgRating)) }}{{ str_repeat('☆', 5 - (int) round($avgRating)) }}</span>
                                                <span class="text-base font-bold text-amber-600 dark:text-amber-400">{{ number_format($avgRating, 1) }}/5</span>
                                            </div>
                                        </div>

                                        @if($survey->comment)
                                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/60 px-4 py-3">
                                                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 mb-1.5">Comentario</p>
                                                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $survey->comment }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0 border-t border-zinc-100 dark:border-zinc-800 px-5 py-3">
                                        <button
                                            @click="open = false"
                                            class="w-full rounded-lg bg-zinc-100 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700 transition-colors"
                                        >
                                            Cerrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
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
