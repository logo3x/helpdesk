<x-filament-panels::page>

    <style>
        @keyframes cssFadeUp {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes cssMsgRight {
            from { opacity: 0; transform: translateX(10px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes cssMsgLeft {
            from { opacity: 0; transform: translateX(-10px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .cs-header { animation: cssFadeUp .25s ease both; }
        .cs-msg-user { animation: cssMsgRight .22s ease both; }
        .cs-msg-bot  { animation: cssMsgLeft  .22s ease both; }
    </style>

    {{-- Metadata --}}
    <div class="cs-header overflow-hidden rounded-xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/80">
        <div class="grid grid-cols-1 gap-0 sm:grid-cols-2 lg:grid-cols-4">
            <div class="border-b border-zinc-100 p-4 dark:border-zinc-800 sm:border-b-0 sm:border-r">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Usuario</div>
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $record->user?->name ?? '—' }}</div>
                <div class="mt-0.5 text-xs text-zinc-400">{{ $record->user?->email }}</div>
            </div>

            <div class="border-b border-zinc-100 p-4 dark:border-zinc-800 sm:border-b-0 sm:border-r lg:border-b-0">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Estado</div>
                <span @class([
                    'inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold',
                    'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-200' => $record->status === 'active',
                    'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' => $record->status === 'escalated',
                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $record->status === 'closed',
                ])>
                    {{ match($record->status) { 'active' => 'Activa', 'escalated' => 'Escalada', 'closed' => 'Cerrada', default => ucfirst($record->status) } }}
                </span>
            </div>

            <div class="border-b border-zinc-100 p-4 dark:border-zinc-800 lg:border-b-0 lg:border-r">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Canal</div>
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ ucfirst($record->channel) }}</div>
            </div>

            <div class="p-4">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-zinc-400">Inicio</div>
                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $record->created_at->format('d/m/Y H:i') }}</div>
                <div class="mt-0.5 text-xs text-zinc-400">{{ $record->created_at->diffForHumans() }}</div>
            </div>
        </div>

        @if ($record->escalatedTicket)
            <div class="border-t border-zinc-100 bg-amber-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-amber-950/20">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">Ticket escalado</div>
                <a href="{{ route('filament.soporte.resources.tickets.edit', $record->escalatedTicket) }}"
                   class="inline-flex items-center gap-2 rounded-lg bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-800 transition hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-200 dark:hover:bg-amber-900/60">
                    <x-heroicon-o-ticket class="size-4" />
                    {{ $record->escalatedTicket->number }} — {{ $record->escalatedTicket->subject }}
                </a>
            </div>
        @endif
    </div>

    {{-- Transcripción --}}
    <div class="mt-6">
        <div class="mb-4 flex items-center gap-2">
            <x-heroicon-o-chat-bubble-left-right class="size-5 text-zinc-400" />
            <h2 class="text-base font-semibold text-zinc-700 dark:text-zinc-300">
                Transcripción
                <span class="ml-1.5 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-500 dark:bg-zinc-800">
                    {{ $record->messages->count() }} mensajes
                </span>
            </h2>
        </div>

        <div class="space-y-3">
            @forelse ($record->messages->sortBy('created_at') as $idx => $msg)
                @if ($msg->role === 'user')
                    <div class="cs-msg-user flex justify-end" style="animation-delay: {{ min($idx * 40, 500) }}ms">
                        <div class="max-w-[85%] overflow-hidden rounded-2xl rounded-br-sm bg-gradient-to-br from-sky-500 to-sky-600 px-4 py-3 text-sm shadow-sm dark:from-sky-600 dark:to-sky-700">
                            <div class="mb-1.5 flex items-center gap-1.5 text-xs text-sky-200">
                                <x-heroicon-m-user class="size-3" />
                                <span class="font-semibold">{{ $record->user?->name ?? 'Usuario' }}</span>
                                <span class="opacity-60">·</span>
                                <span class="opacity-60">{{ $msg->created_at->format('d/m H:i') }}</span>
                            </div>
                            <div class="chat-body text-white">
                                {!! str($msg->content)->markdown()->toHtmlString() !!}
                            </div>
                        </div>
                    </div>
                @elseif ($msg->role === 'assistant')
                    <div class="cs-msg-bot flex justify-start" style="animation-delay: {{ min($idx * 40, 500) }}ms">
                        <div class="flex max-w-[85%] items-start gap-2">
                            <div class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 shadow-sm">
                                <x-heroicon-m-sparkles class="size-3.5 text-white" />
                            </div>
                            <div class="overflow-hidden rounded-2xl rounded-bl-sm bg-white px-4 py-3 text-sm text-zinc-800 shadow-sm ring-1 ring-zinc-100 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700/50">
                                <div class="mb-1.5 flex items-center gap-1.5 text-xs text-zinc-400">
                                    <span class="font-semibold text-indigo-500">Asistente</span>
                                    <span>·</span>
                                    <span>{{ $msg->created_at->format('d/m H:i') }}</span>
                                </div>
                                <div class="chat-body">
                                    {!! str($msg->content)->markdown()->toHtmlString() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex justify-center" style="animation-delay: {{ min($idx * 40, 500) }}ms">
                        <div class="flex items-center gap-2 rounded-full border border-zinc-200/80 bg-white px-3 py-1 text-xs text-zinc-500 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <x-heroicon-m-information-circle class="size-3" />
                            <span>{{ ucfirst($msg->role) }}</span>
                            <span class="text-zinc-300">·</span>
                            <span>{{ $msg->created_at->format('d/m H:i') }}</span>
                        </div>
                    </div>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-zinc-200 py-10 text-center dark:border-zinc-700">
                    <x-heroicon-o-chat-bubble-left class="mx-auto mb-2 size-8 text-zinc-300" />
                    <p class="text-sm italic text-zinc-400">Sin mensajes en esta sesión.</p>
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .chat-body > :first-child { margin-top: 0 !important; }
        .chat-body > :last-child { margin-bottom: 0 !important; }
        .chat-body p { margin: 0.4rem 0; line-height: 1.55; }
        .chat-body h1, .chat-body h2, .chat-body h3 { font-weight: 600; margin: 0.75rem 0 0.4rem; }
        .chat-body h1 { font-size: 1.05rem; }
        .chat-body h2 { font-size: 1rem; }
        .chat-body h3 { font-size: 0.95rem; }
        .chat-body ul, .chat-body ol { margin: 0.4rem 0; padding-left: 1.4rem; }
        .chat-body ul { list-style: disc; }
        .chat-body ol { list-style: decimal; }
        .chat-body li { margin: 0.2rem 0; }
        .chat-body strong { font-weight: 600; }
        .chat-body code { background: rgba(0,0,0,.08); padding: .1rem .3rem; border-radius: .25rem; font-size: .85em; }
        .chat-body hr { border: 0; border-top: 1px solid rgba(0,0,0,.1); margin: .6rem 0; }
    </style>
</x-filament-panels::page>
