<div class="mx-auto max-w-2xl"
     x-data
     x-init="
        // Animar entrada de cards al cargar
        document.querySelectorAll('.stat-card').forEach((el, i) => {
            el.style.animationDelay = (i * 60) + 'ms';
        });
     ">

    {{-- Estilos de animación del chatbot --}}
    <style>
        @keyframes chatFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes chatFadeRight {
            from { opacity: 0; transform: translateX(12px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes chatFadeLeft {
            from { opacity: 0; transform: translateX(-12px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes cardSlideUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes typingDot {
            0%, 100% { transform: translateY(0); opacity: .4; }
            50%       { transform: translateY(-4px); opacity: 1; }
        }
        .msg-user   { animation: chatFadeRight .25s ease both; }
        .msg-bot    { animation: chatFadeLeft  .25s ease both; }
        .stat-card  { animation: cardSlideUp .3s ease both; opacity: 0; }
        .typing-dot { animation: typingDot 1.2s ease-in-out infinite; }
        .typing-dot:nth-child(2) { animation-delay: .2s; }
        .typing-dot:nth-child(3) { animation-delay: .4s; }
    </style>

    {{-- Saludo + mini-cards de estado en una sola fila --}}
    <div class="mb-4">
        @php
            $hour = now()->hour;
            $greeting = match (true) {
                $hour < 12 => 'Buenos días',
                $hour < 19 => 'Buenas tardes',
                default    => 'Buenas noches',
            };
            $firstName = explode(' ', (string) $user?->name)[0] ?? '';
        @endphp

        <div class="flex items-center gap-2">
            {{-- Avatar + saludo --}}
            <div class="flex shrink-0 items-center gap-2.5 mr-2">
                <div class="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-sky-400 to-sky-600 text-sm font-bold text-white shadow-md">
                    {{ $user?->initials() }}
                    <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-zinc-900"></span>
                </div>
                <div>
                    <div class="text-sm font-semibold leading-tight text-zinc-800 dark:text-zinc-100">{{ $greeting }}, {{ $firstName }}</div>
                    <flux:text size="xs" class="text-zinc-400">¿En qué puedo ayudarte?</flux:text>
                </div>
            </div>

            <div class="h-8 w-px bg-zinc-200 dark:bg-zinc-700 shrink-0"></div>

            {{-- Mini cards --}}
            <div class="flex flex-1 gap-2">
                <a href="{{ route('portal.tickets.index') }}" wire:navigate
                   class="stat-card flex flex-1 items-center gap-2 rounded-xl border border-zinc-200/80 bg-white px-3 py-2 shadow-sm transition-all duration-200 hover:border-sky-300 hover:shadow-md hover:-translate-y-0.5 dark:border-zinc-700/80 dark:bg-zinc-800/60 dark:hover:border-sky-600">
                    <div class="h-7 w-7 rounded-lg bg-sky-50 dark:bg-sky-950/50 flex items-center justify-center shrink-0">
                        <flux:icon name="inbox" class="size-3.5 text-sky-500" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold leading-tight text-zinc-900 dark:text-zinc-100">{{ $totalCount }}</div>
                        <flux:text size="xs" class="truncate text-zinc-400">Mis tickets</flux:text>
                    </div>
                </a>

                <a href="{{ route('portal.tickets.index') }}" wire:navigate
                   class="stat-card flex flex-1 items-center gap-2 rounded-xl border border-zinc-200/80 bg-white px-3 py-2 shadow-sm transition-all duration-200 hover:border-amber-300 hover:shadow-md hover:-translate-y-0.5 dark:border-zinc-700/80 dark:bg-zinc-800/60 dark:hover:border-amber-600">
                    <div class="h-7 w-7 rounded-lg bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center shrink-0">
                        <flux:icon name="bolt" class="size-3.5 text-amber-500" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold leading-tight text-zinc-900 dark:text-zinc-100">{{ $openCount }}</div>
                        <flux:text size="xs" class="truncate text-zinc-400">En proceso</flux:text>
                    </div>
                </a>

                <a href="{{ route('portal.tickets.index') }}?status=pendiente_cliente" wire:navigate
                   class="stat-card flex flex-1 items-center gap-2 rounded-xl border px-3 py-2 shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5
                       {{ $waitingCount > 0
                           ? 'border-red-200 bg-red-50 dark:border-red-800/60 dark:bg-red-950/30 hover:border-red-300 dark:hover:border-red-700'
                           : 'border-zinc-200/80 bg-white dark:border-zinc-700/80 dark:bg-zinc-800/60 hover:border-zinc-300 dark:hover:border-zinc-600' }}">
                    <div class="h-7 w-7 rounded-lg {{ $waitingCount > 0 ? 'bg-red-100 dark:bg-red-900/50' : 'bg-zinc-50 dark:bg-zinc-800' }} flex items-center justify-center shrink-0
                        {{ $waitingCount > 0 ? 'animate-pulse' : '' }}">
                        <flux:icon name="exclamation-circle" class="size-3.5 {{ $waitingCount > 0 ? 'text-red-500' : 'text-zinc-400' }}" />
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-bold leading-tight {{ $waitingCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $waitingCount }}</div>
                        <flux:text size="xs" class="{{ $waitingCount > 0 ? 'truncate text-red-400' : 'truncate text-zinc-400' }}">Tu respuesta</flux:text>
                    </div>
                </a>
            </div>
        </div>
    </div>

    {{-- Chat messages --}}
    <div class="mb-3 h-[32rem] overflow-y-auto rounded-2xl border border-zinc-200/80 bg-zinc-50/80 p-4 dark:border-zinc-700/80 dark:bg-zinc-900/80 shadow-inner"
         id="chat-container"
         x-data
         x-on:chat-updated.window="$nextTick(() => { $el.scrollTo({ top: $el.scrollHeight, behavior: 'smooth' }) })">
        <div class="space-y-3">
            @foreach ($history as $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    @if ($msg['role'] === 'user')
                        {{-- Mensaje usuario --}}
                        <div class="msg-user max-w-[80%] rounded-2xl rounded-br-sm bg-gradient-to-br from-sky-500 to-sky-600 px-4 py-2.5 text-sm text-white shadow-sm dark:from-sky-600 dark:to-sky-700">
                            {{ $msg['content'] }}
                        </div>
                    @else
                        {{-- Mensaje bot --}}
                        <div class="msg-bot flex max-w-[85%] flex-col gap-1">
                            <div class="flex items-start gap-2">
                                <div class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 shadow-sm">
                                    <flux:icon name="sparkles" class="size-3 text-white" />
                                </div>
                                <div class="chat-bubble-bot rounded-2xl rounded-bl-sm bg-white px-4 py-3.5 text-sm leading-relaxed text-zinc-800 shadow-sm ring-1 ring-zinc-100 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700/50">
                                    {!! str($msg['content'])->markdown([
                                        'html_input' => 'strip',
                                        'allow_unsafe_links' => false,
                                    ])->toHtmlString() !!}
                                </div>
                            </div>

                            {{-- Feedback solo para mensajes IA persistidos
                                 (no para el saludo inicial ni mensajes de
                                 sistema/escalación). --}}
                            @php
                                $sourceKind = $msg['source_kind'] ?? null;
                                $canRate = ($msg['id'] ?? null) !== null
                                    && in_array($sourceKind, ['kb_high', 'kb_medium', 'llm'], true);
                            @endphp
                            @if ($canRate)
                                <div class="ml-2 flex items-center gap-1 text-xs text-zinc-400">
                                    @if ($msg['helpful'] === null)
                                        <span>¿Te sirvió?</span>
                                        <button
                                            type="button"
                                            wire:click="rateMessage({{ $msg['id'] }}, true)"
                                            class="rounded p-1 hover:bg-zinc-100 hover:text-emerald-600 dark:hover:bg-zinc-700"
                                            title="Sí, me sirvió">
                                            👍
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="rateMessage({{ $msg['id'] }}, false)"
                                            class="rounded p-1 hover:bg-zinc-100 hover:text-rose-600 dark:hover:bg-zinc-700"
                                            title="No me sirvió">
                                            👎
                                        </button>
                                    @elseif ($msg['helpful'] === true)
                                        <span class="font-medium text-emerald-600">👍 Gracias por tu feedback</span>
                                    @else
                                        <span class="font-medium text-rose-600">👎 Tomamos nota — escribe "crear ticket" si necesitas un agente.</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Input --}}
    <form wire:submit="send" class="flex gap-2">
        <flux:input
            wire:model="message"
            placeholder="Escribe tu consulta..."
            autofocus
            class="flex-1 rounded-xl"
        />
        <flux:button type="submit" variant="primary" icon="paper-airplane" class="rounded-xl px-5">
            Enviar
        </flux:button>
    </form>

    <flux:text size="xs" class="mt-2 text-zinc-400">
        Escribe <strong>"crear ticket"</strong> para hablar con un agente · <strong>"escalar: [resumen]"</strong> para crear uno directamente.
    </flux:text>

    {{--
        Estilos locales para el markdown del bot.
        Scope al contenedor para no filtrar al resto de la app.
    --}}
    @push('styles')
    @endpush
    <style>
        .chat-bubble-bot > :first-child { margin-top: 0 !important; }
        .chat-bubble-bot > :last-child { margin-bottom: 0 !important; }

        .chat-bubble-bot p {
            margin: 0.5rem 0;
            line-height: 1.6;
        }

        .chat-bubble-bot h1,
        .chat-bubble-bot h2,
        .chat-bubble-bot h3,
        .chat-bubble-bot h4 {
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            line-height: 1.3;
        }
        .chat-bubble-bot h1 { font-size: 1.15rem; }
        .chat-bubble-bot h2 { font-size: 1.05rem; }
        .chat-bubble-bot h3 { font-size: 0.95rem; color: rgb(82 82 91); }
        .dark .chat-bubble-bot h3 { color: rgb(212 212 216); }

        .chat-bubble-bot ul,
        .chat-bubble-bot ol {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .chat-bubble-bot ul { list-style: disc; }
        .chat-bubble-bot ol { list-style: decimal; }
        .chat-bubble-bot li {
            margin: 0.25rem 0;
            line-height: 1.6;
            padding-left: 0.25rem;
        }
        .chat-bubble-bot li > p { margin: 0; }

        .chat-bubble-bot strong {
            font-weight: 600;
            color: rgb(30 41 59);
        }
        .dark .chat-bubble-bot strong {
            color: rgb(241 245 249);
        }

        .chat-bubble-bot code {
            background: rgb(244 244 245);
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.85em;
            font-family: ui-monospace, SFMono-Regular, monospace;
        }
        .dark .chat-bubble-bot code {
            background: rgb(39 39 42);
        }

        .chat-bubble-bot pre {
            background: rgb(244 244 245);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 0.75rem 0;
            font-size: 0.85em;
        }
        .dark .chat-bubble-bot pre {
            background: rgb(24 24 27);
        }

        .chat-bubble-bot blockquote {
            border-left: 3px solid rgb(212 212 216);
            padding-left: 0.75rem;
            margin: 0.75rem 0;
            color: rgb(82 82 91);
        }
        .dark .chat-bubble-bot blockquote {
            border-color: rgb(82 82 91);
            color: rgb(161 161 170);
        }

        .chat-bubble-bot a {
            color: rgb(14 165 233);
            text-decoration: underline;
        }
        .dark .chat-bubble-bot a {
            color: rgb(56 189 248);
        }

        .chat-bubble-bot hr {
            border: 0;
            border-top: 1px solid rgb(228 228 231);
            margin: 0.75rem 0;
        }
        .dark .chat-bubble-bot hr {
            border-top-color: rgb(63 63 70);
        }
    </style>
</div>
