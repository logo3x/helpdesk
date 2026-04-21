<div class="mx-auto max-w-2xl">
    <flux:heading size="xl" class="mb-6">Asistente virtual</flux:heading>

    {{-- Chat messages --}}
    <div class="mb-4 h-[32rem] overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900"
         id="chat-container"
         x-data
         x-on:chat-updated.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight })">
        <div class="space-y-4">
            @foreach ($history as $msg)
                <div class="flex {{ $msg['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    @if ($msg['role'] === 'user')
                        {{-- Mensajes del usuario: burbuja azul compacta --}}
                        <div class="max-w-[80%] rounded-2xl rounded-br-sm bg-sky-500 px-4 py-2 text-sm text-white dark:bg-sky-600">
                            {{ $msg['content'] }}
                        </div>
                    @else
                        {{-- Mensajes del bot: burbuja con markdown estilizado.
                             html_input=strip elimina HTML inline para prevenir
                             XSS desde contenido de KB o del LLM externo (un
                             supervisor malicioso podría publicar un KB con
                             <img onerror=...> o el LLM devolver HTML). --}}
                        <div class="chat-bubble-bot max-w-[85%] rounded-2xl rounded-bl-sm bg-white px-5 py-4 text-sm leading-relaxed text-zinc-800 shadow-sm dark:bg-zinc-800 dark:text-zinc-200">
                            {!! str($msg['content'])->markdown([], [
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                            ])->toHtmlString() !!}
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
            class="flex-1"
        />
        <flux:button type="submit" variant="primary" icon="paper-airplane">
            Enviar
        </flux:button>
    </form>

    <flux:text size="sm" class="mt-2 text-zinc-400">
        Escribe <strong>"crear ticket"</strong> para hablar con un agente. Usa <strong>"escalar: [resumen]"</strong> para crear un ticket directamente.
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
