<div>

    <style>
        @keyframes msgSlideIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes headerFade {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .msg-anim { animation: msgSlideIn .3s ease both; }
        .header-anim { animation: headerFade .25s ease both; }
    </style>

    {{-- Header --}}
    <div class="mb-6 header-anim">
        <div class="mb-3">
            <flux:button :href="route('portal.tickets.index')" variant="ghost" icon="arrow-left" size="sm" wire:navigate>
                Volver a mis tickets
            </flux:button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200/80 bg-white p-5 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <span class="inline-flex items-center rounded-md bg-zinc-100 px-2.5 py-1 text-sm font-mono font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
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
                    'alta', 'critica' => 'red',
                    default => 'zinc',
                }">{{ $ticket->priority->getLabel() }}</flux:badge>
            </div>

            <flux:heading size="lg" class="leading-snug">{{ $ticket->subject }}</flux:heading>

            <div class="mt-2 flex flex-wrap gap-3 text-xs text-zinc-400">
                <span class="flex items-center gap-1">
                    <flux:icon name="clock" class="size-3" />
                    Creado {{ $ticket->created_at->diffForHumans() }}
                </span>
                @if ($ticket->category)
                    <span class="flex items-center gap-1">
                        <flux:icon name="tag" class="size-3" />
                        {{ $ticket->category->name }}
                    </span>
                @endif
                @if ($ticket->assignee)
                    <span class="flex items-center gap-1">
                        <flux:icon name="user" class="size-3" />
                        {{ $ticket->assignee->name }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Adjuntos --}}
    @if ($ticket->getMedia('attachments')->count())
        <div class="mb-6">
            <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                <flux:icon name="paper-clip" class="size-3.5" />
                Adjuntos
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($ticket->getMedia('attachments') as $media)
                    <a href="{{ $media->getUrl() }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm transition-all duration-150 hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                        <flux:icon name="paper-clip" class="size-4 text-zinc-400" />
                        {{ $media->file_name }}
                        <span class="text-xs text-zinc-400">({{ Number::fileSize($media->size) }})</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Encabezado conversación --}}
    <div class="mb-4 flex items-center gap-2">
        <flux:icon name="chat-bubble-left-right" class="size-5 text-zinc-400" />
        <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400">Conversación</flux:heading>
        <span class="ml-auto rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:bg-zinc-800">
            {{ 1 + count($comments) }} mensaje(s)
        </span>
    </div>

    {{-- Hilo --}}
    <div class="space-y-4">
        {{-- Mensaje original --}}
        <div class="msg-anim flex gap-3" style="animation-delay: 0ms">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-sky-600 text-xs font-bold text-white shadow-sm">
                {{ $ticket->requester?->initials() ?? '?' }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="mb-1.5 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                    <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                        {{ $ticket->requester_id === auth()->id() ? 'Tú' : $ticket->requester?->name }}
                    </span>
                    <flux:badge size="sm" color="sky">Mensaje original</flux:badge>
                    <span class="text-zinc-300">·</span>
                    <span>{{ $ticket->created_at->diffForHumans() }}</span>
                </div>
                <div class="ticket-msg rounded-xl rounded-tl-sm border border-sky-100 bg-sky-50/80 px-4 py-3 text-sm text-zinc-800 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-zinc-100">
                    {!! str($ticket->description)->markdown()->toHtmlString() !!}
                </div>
            </div>
        </div>

        {{-- Comentarios --}}
        @forelse ($comments as $idx => $comment)
            @if ($comment->is_system_event)
                <div class="msg-anim flex items-center gap-3 py-1" style="animation-delay: {{ ($idx + 1) * 50 }}ms">
                    <span class="h-px flex-1 bg-zinc-200/60 dark:bg-zinc-700/60"></span>
                    <div class="flex items-center gap-1.5 rounded-full border border-zinc-200/80 bg-white px-3 py-1 text-xs text-zinc-500 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">
                        <flux:icon name="arrow-path" class="size-3 text-zinc-400" />
                        <span class="ticket-msg-system">
                            {!! str($comment->body)->markdown()->toHtmlString() !!}
                        </span>
                        @if ($comment->user)
                            <span class="text-zinc-300 dark:text-zinc-600">·</span>
                            <span>{{ $comment->user->name }}</span>
                        @endif
                        <span class="text-zinc-300 dark:text-zinc-600">·</span>
                        <span>{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <span class="h-px flex-1 bg-zinc-200/60 dark:bg-zinc-700/60"></span>
                </div>
            @else
                @php
                    $isMine = $comment->user_id === auth()->id();
                    $isRequester = $comment->user_id === $ticket->requester_id;
                    $bubbleClasses = $isRequester
                        ? 'border-sky-100 bg-sky-50/80 dark:border-sky-900/50 dark:bg-sky-950/40 rounded-tl-sm'
                        : 'border-emerald-100 bg-emerald-50/80 dark:border-emerald-900/50 dark:bg-emerald-950/40 rounded-tl-sm';
                    $avatarGradient = $isRequester
                        ? 'from-sky-400 to-sky-600'
                        : 'from-emerald-500 to-emerald-700';
                    $roleLabel = $isRequester ? 'Solicitante' : 'Soporte';
                    $roleBadgeColor = $isRequester ? 'sky' : 'emerald';
                @endphp

                <div class="msg-anim flex gap-3" style="animation-delay: {{ ($idx + 1) * 50 }}ms">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br {{ $avatarGradient }} text-xs font-bold text-white shadow-sm">
                        {{ $comment->user?->initials() ?? '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="mb-1.5 flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $isMine ? 'Tú' : $comment->user?->name }}
                            </span>
                            <flux:badge size="sm" :color="$roleBadgeColor">{{ $roleLabel }}</flux:badge>
                            <span class="text-zinc-300">·</span>
                            <span>{{ $comment->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="ticket-msg rounded-xl border px-4 py-3 text-sm text-zinc-800 shadow-sm dark:text-zinc-100 {{ $bubbleClasses }}">
                            {!! str($comment->body)->markdown()->toHtmlString() !!}
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="rounded-xl border border-dashed border-zinc-200 py-8 text-center dark:border-zinc-700">
                <flux:icon name="chat-bubble-left" class="mx-auto mb-2 size-8 text-zinc-300" />
                <flux:text size="sm" class="text-zinc-400">Sin respuestas aún. Cuando un agente comente verás su mensaje aquí.</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Estilos markdown --}}
    <style>
        .ticket-msg-system > :first-child,
        .ticket-msg-system p { display: inline; margin: 0; }
        .ticket-msg-system strong { font-weight: 600; }
        .ticket-msg-system code {
            background: rgba(0,0,0,.06); padding: .05rem .3rem;
            border-radius: .2rem; font-size: .85em;
        }
        .dark .ticket-msg-system code { background: rgba(255,255,255,.08); }

        .ticket-msg > :first-child { margin-top: 0 !important; }
        .ticket-msg > :last-child { margin-bottom: 0 !important; }
        .ticket-msg p { margin: .4rem 0; line-height: 1.55; }
        .ticket-msg h1 { font-size: 1.15rem; font-weight: 600; margin: .75rem 0 .4rem; }
        .ticket-msg h2 { font-size: 1.05rem; font-weight: 600; margin: .75rem 0 .4rem; }
        .ticket-msg h3 { font-size: .95rem; font-weight: 600; margin: .5rem 0 .4rem; }
        .ticket-msg ul, .ticket-msg ol { margin: .5rem 0; padding-left: 1.4rem; }
        .ticket-msg ul { list-style: disc; }
        .ticket-msg ol { list-style: decimal; }
        .ticket-msg li { margin: .2rem 0; }
        .ticket-msg strong { font-weight: 600; }
        .ticket-msg em { font-style: italic; }
        .ticket-msg code {
            background: rgba(0,0,0,.08); padding: .1rem .35rem;
            border-radius: .25rem; font-size: .85em;
        }
        .dark .ticket-msg code { background: rgba(255,255,255,.12); }
        .ticket-msg pre {
            background: rgba(0,0,0,.06); padding: .6rem;
            border-radius: .4rem; overflow-x: auto; margin: .5rem 0;
        }
        .ticket-msg pre code { background: transparent; padding: 0; }
        .ticket-msg a { color: #0369a1; text-decoration: underline; }
        .dark .ticket-msg a { color: #38bdf8; }
        .ticket-msg blockquote {
            border-left: 3px solid rgba(0,0,0,.15);
            padding-left: .75rem; color: rgba(0,0,0,.6); margin: .5rem 0;
        }
        .dark .ticket-msg blockquote {
            border-color: rgba(255,255,255,.2); color: rgba(255,255,255,.7);
        }
    </style>

    {{-- Formulario de respuesta --}}
    @if ($ticket->status->isOpen())
        <div class="mt-8 overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80"
             style="animation: msgSlideIn .35s ease .15s both">
            <div class="flex items-center gap-2 border-b border-zinc-100 bg-zinc-50/80 px-5 py-3 dark:border-zinc-800 dark:bg-zinc-800/50">
                <flux:icon name="pencil-square" class="size-4 text-zinc-400" />
                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Tu respuesta</span>
            </div>
            <div class="p-5">
                <form wire:submit="addComment">
                    <flux:textarea
                        wire:model="commentBody"
                        placeholder="Escribe aquí más información, una pregunta o una actualización..."
                        rows="3"
                        required
                    />
                    <div class="mt-3 flex justify-end">
                        <flux:button type="submit" variant="primary" icon="paper-airplane">
                            Enviar respuesta
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="mt-8 rounded-xl border border-dashed border-zinc-300 py-6 text-center dark:border-zinc-600">
            <flux:icon name="lock-closed" class="mx-auto mb-2 size-6 text-zinc-400" />
            <flux:text size="sm">Este ticket está <strong>{{ $ticket->status->getLabel() }}</strong> — no se pueden agregar comentarios.</flux:text>
        </div>
    @endif
</div>
