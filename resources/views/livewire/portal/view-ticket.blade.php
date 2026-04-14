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
    <div class="mb-8 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:text class="whitespace-pre-wrap">{{ $ticket->description }}</flux:text>
    </div>

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
