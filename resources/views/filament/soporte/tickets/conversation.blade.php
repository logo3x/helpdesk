@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();
    $comments = $ticket->comments()->with(['user:id,name', 'media'])->oldest()->oldest('id')->get();
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
    // Colores con transparencia para que funcionen en modo claro y oscuro.
    $bubble = [
        'requester' => 'rounded-bl-sm border-gray-950/15 bg-gray-950/[0.04] dark:border-white/15 dark:bg-white/5',
        'staff' => 'rounded-br-sm border-sky-500/35 bg-sky-500/10',
        'private' => 'rounded-br-sm border-amber-500/45 bg-amber-500/10',
    ];
    $badge = 'inline-block rounded-full px-2 py-px text-[0.7rem] font-semibold';
@endphp

<div class="flex flex-col gap-3.5">
    @forelse ($comments as $comment)
        @php
            $fromRequester = $comment->user_id === $ticket->requester_id;
            $isSystem = $comment->is_system_event;
            $kind = $fromRequester ? 'requester' : ($comment->is_private ? 'private' : 'staff');
        @endphp

        @if ($isSystem && $comment->is_private)
            {{-- Evento interno del sistema: línea discreta centrada --}}
            <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                <div class="h-px flex-1 bg-gray-950/15 dark:bg-white/15"></div>
                <span class="text-center">
                    <strong>{{ $eventLabels[$comment->event_type] ?? 'Evento' }}</strong>
                    · {{ \Illuminate\Support\Str::limit(strip_tags($comment->body), 140) }}
                    · {{ $comment->created_at->format('d/m/Y H:i') }}
                </span>
                <div class="h-px flex-1 bg-gray-950/15 dark:bg-white/15"></div>
            </div>
        @else
            <div @class(['flex', 'justify-start' => $fromRequester, 'justify-end' => ! $fromRequester])>
                <div class="max-w-[85%] rounded-2xl border px-4 py-3 {{ $bubble[$kind] }}">
                    <div class="mb-1 flex flex-wrap items-center gap-2 text-xs">
                        <strong class="text-[0.8125rem] text-gray-950 dark:text-white">{{ $isSystem ? '⚙ Sistema' : ($comment->user?->name ?? '—') }}</strong>

                        @if ($fromRequester)
                            <span class="{{ $badge }} bg-gray-950/10 dark:bg-white/10">Solicitante</span>
                        @elseif ($comment->is_private)
                            <span class="{{ $badge }} bg-amber-500/30">🔒 Nota interna</span>
                        @elseif ($isSystem)
                            <span class="{{ $badge }} bg-gray-950/10 dark:bg-white/10">{{ $eventLabels[$comment->event_type] ?? 'Mensaje automático' }}</span>
                        @endif

                        <span class="text-gray-500 dark:text-gray-400" title="{{ $comment->created_at->format('d/m/Y H:i') }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </span>
                    </div>

                    <div class="fi-prose max-w-none text-sm [overflow-wrap:anywhere]">
                        {!! \App\Services\MarkdownRenderer::safe($comment->body) !!}
                    </div>

                    @if ($comment->media->isNotEmpty())
                        <x-attachment-viewer :files="$comment->media->where('collection_name', 'attachments')" compact class="mt-2.5" />
                    @endif
                </div>
            </div>
        @endif
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay mensajes. Usa <strong>Responder</strong> para escribirle al solicitante.</p>
    @endforelse
</div>
