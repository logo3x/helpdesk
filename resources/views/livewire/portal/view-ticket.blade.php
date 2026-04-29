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

    {{-- Adjuntos --}}
    @if ($ticket->getMedia('attachments')->count())
        <div class="mb-6">
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

    {{-- Conversación: descripción inicial + comentarios como un hilo
         de chat con burbujas alineadas según quién escribió.
         Solicitante → derecha en sky.
         Staff (agente / supervisor / admin) → izquierda en blanco/gris.   --}}
    <flux:heading size="lg" class="mb-4">Conversación</flux:heading>

    {{-- Hilo tipo email: todo alineado a la izquierda. Cada mensaje
         se distingue por color de fondo + barra lateral izquierda
         del color del rol (sky=solicitante, emerald=soporte).      --}}
    <div class="space-y-4">
        {{-- Mensaje original del solicitante (la descripción del ticket) --}}
        <div class="flex gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sky-500 text-xs font-semibold text-white shadow">
                {{ $ticket->requester?->initials() ?? '?' }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ $ticket->requester_id === auth()->id() ? 'Tú' : $ticket->requester?->name }}
                    </span>
                    <flux:badge size="sm" color="sky">Mensaje original</flux:badge>
                    <span>·</span>
                    <span>{{ $ticket->created_at->diffForHumans() }}</span>
                </div>
                <div class="ticket-msg rounded-lg border-l-4 border-sky-500 bg-sky-50 px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-sky-400 dark:bg-sky-950/40 dark:text-zinc-100">
                    {!! str($ticket->description)->markdown()->toHtmlString() !!}
                </div>
            </div>
        </div>

        {{-- Comentarios subsiguientes --}}
        @forelse ($comments as $comment)
            {{-- Eventos del sistema (traslado, etc.): divisor de timeline
                 en lugar de burbuja, para distinguirlos visualmente de
                 una respuesta humana. --}}
            @if ($comment->is_system_event)
                <div class="flex items-center gap-3 py-2">
                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                    <div class="flex items-center gap-2 rounded-full border border-zinc-200 bg-white px-3 py-1.5 text-xs text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        <span class="ticket-msg-system">
                            {!! str($comment->body)->markdown()->toHtmlString() !!}
                        </span>
                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                        @if ($comment->user)
                            <span class="text-zinc-500 dark:text-zinc-500">{{ $comment->user->name }}</span>
                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                        @endif
                        <span class="text-zinc-400">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></span>
                </div>
            @else
                @php
                    $isMine = $comment->user_id === auth()->id();
                    $isRequester = $comment->user_id === $ticket->requester_id;
                    $bubbleClasses = $isRequester
                        ? 'border-sky-500 bg-sky-50 dark:border-sky-400 dark:bg-sky-950/40'
                        : 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-950/40';
                    $avatarColor = $isRequester ? 'bg-sky-500' : 'bg-emerald-600';
                    $roleLabel = $isRequester ? 'Solicitante' : 'Soporte';
                    $roleBadgeColor = $isRequester ? 'sky' : 'emerald';
                @endphp

                <div class="flex gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white shadow {{ $avatarColor }}">
                        {{ $comment->user?->initials() ?? '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $isMine ? 'Tú' : $comment->user?->name }}
                            </span>
                            <flux:badge size="sm" :color="$roleBadgeColor">{{ $roleLabel }}</flux:badge>
                            <span>·</span>
                            <span>{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="ticket-msg rounded-lg border-l-4 px-4 py-3 text-sm text-zinc-800 shadow-sm dark:text-zinc-100 {{ $bubbleClasses }}">
                            {!! str($comment->body)->markdown()->toHtmlString() !!}
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="rounded-lg border border-dashed border-zinc-300 py-6 text-center dark:border-zinc-600">
                <flux:text class="text-zinc-500">Sin respuestas aún. Cuando un agente comente verás su mensaje aquí.</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Estilos para el markdown dentro de las burbujas --}}
    <style>
        /* Eventos del sistema renderizan inline (sin saltos de párrafo) */
        .ticket-msg-system > :first-child,
        .ticket-msg-system p { display: inline; margin: 0; }
        .ticket-msg-system strong { font-weight: 600; }
        .ticket-msg-system code {
            background: rgba(0, 0, 0, 0.06);
            padding: 0.05rem 0.3rem;
            border-radius: 0.2rem;
            font-size: 0.85em;
        }
        .dark .ticket-msg-system code { background: rgba(255, 255, 255, 0.08); }

        .ticket-msg > :first-child { margin-top: 0 !important; }
        .ticket-msg > :last-child { margin-bottom: 0 !important; }
        .ticket-msg p { margin: 0.4rem 0; line-height: 1.55; }
        .ticket-msg h1 { font-size: 1.15rem; font-weight: 600; margin: 0.75rem 0 0.4rem; }
        .ticket-msg h2 { font-size: 1.05rem; font-weight: 600; margin: 0.75rem 0 0.4rem; }
        .ticket-msg h3 { font-size: 0.95rem; font-weight: 600; margin: 0.5rem 0 0.4rem; }
        .ticket-msg ul, .ticket-msg ol { margin: 0.5rem 0; padding-left: 1.4rem; }
        .ticket-msg ul { list-style: disc; }
        .ticket-msg ol { list-style: decimal; }
        .ticket-msg li { margin: 0.2rem 0; }
        .ticket-msg strong { font-weight: 600; }
        .ticket-msg em { font-style: italic; }
        .ticket-msg code {
            background: rgba(0, 0, 0, 0.08);
            padding: 0.1rem 0.35rem;
            border-radius: 0.25rem;
            font-size: 0.85em;
        }
        .dark .ticket-msg code { background: rgba(255, 255, 255, 0.12); }
        .ticket-msg pre {
            background: rgba(0, 0, 0, 0.06);
            padding: 0.6rem;
            border-radius: 0.4rem;
            overflow-x: auto;
            margin: 0.5rem 0;
        }
        .ticket-msg pre code { background: transparent; padding: 0; }
        .ticket-msg a { color: #0369a1; text-decoration: underline; }
        .dark .ticket-msg a { color: #38bdf8; }
        .ticket-msg blockquote {
            border-left: 3px solid rgba(0, 0, 0, 0.15);
            padding-left: 0.75rem;
            color: rgba(0, 0, 0, 0.6);
            margin: 0.5rem 0;
        }
        .dark .ticket-msg blockquote { border-color: rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); }
    </style>

    {{-- Agregar comentario --}}
    @if ($ticket->status->isOpen())
        <div class="mt-8 rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <form wire:submit="addComment">
                <flux:textarea
                    wire:model="commentBody"
                    label="Tu respuesta"
                    placeholder="Escribe aquí más información, una pregunta o una actualización..."
                    rows="3"
                    required
                />
                <div class="mt-3 flex justify-end">
                    <flux:button type="submit" variant="primary" icon="paper-airplane">
                        Enviar
                    </flux:button>
                </div>
            </form>
        </div>
    @else
        <div class="mt-8 rounded-lg border border-dashed border-zinc-300 py-5 text-center dark:border-zinc-600">
            <flux:icon name="lock-closed" class="mx-auto mb-2 size-6 text-zinc-400" />
            <flux:text>Este ticket está <strong>{{ $ticket->status->getLabel() }}</strong> — no se pueden agregar comentarios.</flux:text>
        </div>
    @endif
</div>
