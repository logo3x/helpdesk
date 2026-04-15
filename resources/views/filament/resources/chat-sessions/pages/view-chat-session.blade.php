<x-filament-panels::page>
    {{-- Metadata --}}
    <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Usuario</div>
            <div class="mt-1 font-medium">{{ $record->user?->name ?? '—' }}</div>
            <div class="text-xs text-gray-500">{{ $record->user?->email }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Estado</div>
            <div class="mt-1">
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium',
                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $record->status === 'active',
                    'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' => $record->status === 'escalated',
                    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' => $record->status === 'closed',
                ])>{{ ucfirst($record->status) }}</span>
            </div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Canal</div>
            <div class="mt-1 font-medium">{{ ucfirst($record->channel) }}</div>
        </div>
        <div>
            <div class="text-xs uppercase tracking-wide text-gray-500">Inicio</div>
            <div class="mt-1 font-medium">{{ $record->created_at->format('d/m/Y H:i') }}</div>
            <div class="text-xs text-gray-500">{{ $record->created_at->diffForHumans() }}</div>
        </div>

        @if ($record->escalatedTicket)
            <div class="sm:col-span-2 lg:col-span-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Ticket escalado</div>
                <a href="{{ route('filament.soporte.resources.tickets.edit', $record->escalatedTicket) }}"
                   class="mt-1 inline-flex items-center gap-2 rounded-md bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-200">
                    {{ $record->escalatedTicket->number }} — {{ $record->escalatedTicket->subject }}
                </a>
            </div>
        @endif
    </div>

    {{-- Transcript --}}
    <div class="mt-6">
        <h2 class="mb-4 text-lg font-semibold">Transcripción ({{ $record->messages->count() }} mensajes)</h2>

        <div class="chat-transcript space-y-3">
            @forelse ($record->messages->sortBy('created_at') as $msg)
                <div class="flex {{ $msg->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div @class([
                        'max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm',
                        'rounded-br-sm bg-sky-500 text-white' => $msg->role === 'user',
                        'rounded-bl-sm bg-white text-gray-800 dark:bg-gray-800 dark:text-gray-100' => $msg->role !== 'user',
                    ])>
                        <div class="mb-1 flex items-center gap-2 text-xs opacity-75">
                            <span class="font-semibold">
                                @if ($msg->role === 'user')
                                    👤 {{ $record->user?->name ?? 'Usuario' }}
                                @elseif ($msg->role === 'assistant')
                                    🤖 Asistente
                                @else
                                    {{ ucfirst($msg->role) }}
                                @endif
                            </span>
                            <span>·</span>
                            <span>{{ $msg->created_at->format('d/m H:i:s') }}</span>
                        </div>
                        <div class="chat-body">
                            {!! str($msg->content)->markdown()->toHtmlString() !!}
                        </div>
                    </div>
                </div>
            @empty
                <p class="italic text-gray-500">Sin mensajes en esta sesión.</p>
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
        .chat-body code { background: rgba(0,0,0,0.08); padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
        .chat-body hr { border: 0; border-top: 1px solid rgba(0,0,0,0.1); margin: 0.6rem 0; }
    </style>
</x-filament-panels::page>
