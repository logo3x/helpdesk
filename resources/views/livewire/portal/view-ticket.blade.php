<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="mb-2 flex items-center gap-2">
            <flux:button :href="route('portal.tickets.index')" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                Volver
            </flux:button>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge size="lg" color="zinc">{{ $ticket->number }}</flux:badge>
            <flux:badge size="lg" :color="match($ticket->status->value) {
                'nuevo' => 'sky',
                'asignado' => 'blue',
                'en_progreso' => 'amber',
                'pendiente_cliente' => 'zinc',
                'resuelto' => 'green',
                'cerrado' => 'zinc',
                'reabierto' => 'red',
                default => 'zinc',
            }">{{ $ticket->status->getLabel() }}</flux:badge>
            <flux:badge size="lg" :color="match($ticket->priority->value) {
                'planificada' => 'zinc',
                'baja' => 'sky',
                'media' => 'amber',
                'alta', 'critica' => 'red',
                default => 'zinc',
            }">{{ $ticket->priority->getLabel() }}</flux:badge>
        </div>
        <flux:heading size="xl" class="mt-2">{{ $ticket->subject }}</flux:heading>
        <flux:text class="mt-1">
            Creado {{ $ticket->created_at->diffForHumans() }}
            · Categoría: {{ $ticket->category?->name ?? '—' }}
            @if ($ticket->assignee)
                · Asignado a: {{ $ticket->assignee->name }}
            @endif
        </flux:text>
    </div>

    {{-- Descripción --}}
    <div class="ticket-description mb-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
        {!! str($ticket->description)->markdown()->toHtmlString() !!}
    </div>

    {{-- Estilos para markdown de la descripción (mismos que el chatbot) --}}
    <style>
        .ticket-description > :first-child { margin-top: 0 !important; }
        .ticket-description > :last-child { margin-bottom: 0 !important; }
        .ticket-description p { margin: 0.5rem 0; line-height: 1.6; }
        .ticket-description h1 { font-size: 1.25rem; font-weight: 600; margin: 1rem 0 0.5rem; }
        .ticket-description h2 { font-size: 1.1rem; font-weight: 600; margin: 1rem 0 0.5rem; }
        .ticket-description h3 { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
        .ticket-description ul, .ticket-description ol { margin: 0.5rem 0; padding-left: 1.5rem; }
        .ticket-description ul { list-style: disc; }
        .ticket-description ol { list-style: decimal; }
        .ticket-description li { margin: 0.25rem 0; }
        .ticket-description strong { font-weight: 600; }
        .ticket-description em { font-style: italic; color: rgb(113 113 122); }
        .ticket-description hr { border: 0; border-top: 1px solid rgb(228 228 231); margin: 1rem 0; }
        .dark .ticket-description hr { border-top-color: rgb(63 63 70); }
        .ticket-description code { background: rgb(228 228 231); padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.85em; }
        .dark .ticket-description code { background: rgb(39 39 42); }
    </style>

    {{-- Adjuntos --}}
    @if ($ticket->getMedia('attachments')->count())
        <div class="mb-8">
            <flux:text class="mb-2 text-sm font-medium">Adjuntos</flux:text>
            <div class="flex flex-wrap gap-2">
                @foreach ($ticket->getMedia('attachments') as $media)
                    <a href="{{ $media->getUrl() }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                        <flux:icon name="paper-clip" class="size-4" />
                        {{ $media->file_name }}
                        <flux:text size="xs" class="text-zinc-400">({{ Number::fileSize($media->size) }})</flux:text>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Comentarios --}}
    <flux:heading size="lg" class="mb-4">Comentarios</flux:heading>

    <div class="space-y-4">
        @forelse ($comments as $comment)
            <div class="rounded-lg border p-4 {{ $comment->user_id === $ticket->requester_id
                ? 'border-sky-200 bg-sky-50 dark:border-sky-800 dark:bg-sky-950'
                : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900' }}">
                <div class="mb-1 flex items-center justify-between">
                    <flux:badge size="sm" :color="$comment->user_id === $ticket->requester_id ? 'sky' : 'zinc'">
                        {{ $comment->user->name }}
                    </flux:badge>
                    <flux:text size="xs">{{ $comment->created_at->diffForHumans() }}</flux:text>
                </div>
                <flux:text class="whitespace-pre-wrap">{{ $comment->body }}</flux:text>
            </div>
        @empty
            <flux:text class="italic text-zinc-400">Sin comentarios aún.</flux:text>
        @endforelse
    </div>

    {{-- Agregar comentario --}}
    @if ($ticket->status->isOpen())
        <div class="mt-6 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <form wire:submit="addComment">
                <flux:textarea
                    wire:model="commentBody"
                    label="Agregar comentario"
                    placeholder="Escribe un comentario o más información..."
                    rows="3"
                    required
                />
                <div class="mt-3 flex justify-end">
                    <flux:button type="submit" variant="primary" size="sm">
                        Enviar comentario
                    </flux:button>
                </div>
            </form>
        </div>
    @else
        <div class="mt-6 rounded-lg border border-dashed border-zinc-300 py-4 text-center dark:border-zinc-600">
            <flux:text>Este ticket está {{ $ticket->status->getLabel() }} — no se pueden agregar comentarios.</flux:text>
        </div>
    @endif
</div>
