@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();
    $comments = $ticket->comments()->with('user:id,name')->oldest()->oldest('id')->get();
    $markdown = fn (?string $text) => str((string) $text)->markdown([
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ])->toHtmlString();
    $eventLabels = [
        'assigned' => '👤 Asignado',
        'transferred' => '🔄 Trasladado',
        'resolved' => '✓ Resuelto',
        'reopened' => '↩ Reabierto',
        'recalibrated' => '⚖ Prioridad recalibrada',
        'customer_reminder' => '⏰ Recordatorio al cliente',
        'closed_no_response' => '🔒 Cerrado sin respuesta',
        'auto_closed' => '🔒 Cierre automático',
    ];
@endphp

<div class="space-y-4">
    @forelse ($comments as $comment)
        @php
            $fromRequester = $comment->user_id === $ticket->requester_id;
            $isSystem = $comment->is_system_event;
        @endphp

        @if ($isSystem && $comment->is_private)
            {{-- Evento interno del sistema: línea discreta centrada --}}
            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
                <span class="text-center">
                    <span class="font-semibold">{{ $eventLabels[$comment->event_type] ?? 'Evento' }}</span>
                    · {{ \Illuminate\Support\Str::limit(strip_tags($comment->body), 140) }}
                    · {{ $comment->created_at->format('d/m/Y H:i') }}
                </span>
                <div class="h-px flex-1 bg-gray-200 dark:bg-white/10"></div>
            </div>
        @else
            <div @class(['flex', 'justify-start' => $fromRequester, 'justify-end' => ! $fromRequester])>
                <div @class([
                    'max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm ring-1',
                    'rounded-bl-sm bg-white ring-gray-200 dark:bg-white/5 dark:ring-white/10' => $fromRequester,
                    'rounded-br-sm bg-amber-50 ring-amber-300 dark:bg-amber-500/10 dark:ring-amber-500/40' => ! $fromRequester && $comment->is_private,
                    'rounded-br-sm bg-primary-50 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => ! $fromRequester && ! $comment->is_private,
                ])>
                    <div class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold text-gray-900 dark:text-white">
                            {{ $isSystem ? '⚙ Sistema' : ($comment->user?->name ?? '—') }}
                        </span>

                        @if ($fromRequester)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-white/10 dark:text-gray-300">Solicitante</span>
                        @elseif ($comment->is_private)
                            <span class="rounded-full bg-amber-200 px-2 py-0.5 font-medium text-amber-900 dark:bg-amber-500/30 dark:text-amber-100">🔒 Nota interna</span>
                        @elseif ($isSystem)
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $eventLabels[$comment->event_type] ?? 'Mensaje automático' }}</span>
                        @endif

                        <span class="text-gray-500 dark:text-gray-400" title="{{ $comment->created_at->format('d/m/Y H:i') }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </span>
                    </div>

                    <div class="fi-prose max-w-none break-words text-gray-800 dark:text-gray-200">
                        {!! $markdown($comment->body) !!}
                    </div>
                </div>
            </div>
        @endif
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay mensajes. Usa <strong>Responder</strong> para escribirle al solicitante.</p>
    @endforelse
</div>
