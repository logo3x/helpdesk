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
    // Estilos en línea: los paneles Filament no incluyen las utilidades de
    // Tailwind, así que las clases tipo bg-amber-50 no tendrían efecto.
    // Colores con transparencia para que funcionen en modo claro y oscuro.
    $bubble = [
        'requester' => 'background: rgba(128,128,128,.08); border: 1px solid rgba(128,128,128,.25); border-bottom-left-radius: 0.25rem;',
        'staff' => 'background: rgba(14,165,233,.10); border: 1px solid rgba(14,165,233,.35); border-bottom-right-radius: 0.25rem;',
        'private' => 'background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.45); border-bottom-right-radius: 0.25rem;',
    ];
    $badge = 'display: inline-block; padding: 0.05rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600;';
@endphp

<div style="display: flex; flex-direction: column; gap: 0.875rem;">
    @forelse ($comments as $comment)
        @php
            $fromRequester = $comment->user_id === $ticket->requester_id;
            $isSystem = $comment->is_system_event;
            $kind = $fromRequester ? 'requester' : ($comment->is_private ? 'private' : 'staff');
        @endphp

        @if ($isSystem && $comment->is_private)
            {{-- Evento interno del sistema: línea discreta centrada --}}
            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.75rem; opacity: .7;">
                <div style="height: 1px; flex: 1; background: rgba(128,128,128,.3);"></div>
                <span style="text-align: center;">
                    <strong>{{ $eventLabels[$comment->event_type] ?? 'Evento' }}</strong>
                    · {{ \Illuminate\Support\Str::limit(strip_tags($comment->body), 140) }}
                    · {{ $comment->created_at->format('d/m/Y H:i') }}
                </span>
                <div style="height: 1px; flex: 1; background: rgba(128,128,128,.3);"></div>
            </div>
        @else
            <div style="display: flex; justify-content: {{ $fromRequester ? 'flex-start' : 'flex-end' }};">
                <div style="max-width: 85%; padding: 0.75rem 1rem; border-radius: 1rem; {{ $bubble[$kind] }}">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; font-size: 0.75rem;">
                        <strong style="font-size: 0.8125rem;">{{ $isSystem ? '⚙ Sistema' : ($comment->user?->name ?? '—') }}</strong>

                        @if ($fromRequester)
                            <span style="{{ $badge }} background: rgba(128,128,128,.18);">Solicitante</span>
                        @elseif ($comment->is_private)
                            <span style="{{ $badge }} background: rgba(245,158,11,.30);">🔒 Nota interna</span>
                        @elseif ($isSystem)
                            <span style="{{ $badge }} background: rgba(128,128,128,.18);">{{ $eventLabels[$comment->event_type] ?? 'Mensaje automático' }}</span>
                        @endif

                        <span style="opacity: .65;" title="{{ $comment->created_at->format('d/m/Y H:i') }}">
                            {{ $comment->created_at->diffForHumans() }}
                        </span>
                    </div>

                    <div class="fi-prose" style="max-width: none; overflow-wrap: anywhere; font-size: 0.875rem;">
                        {!! $markdown($comment->body) !!}
                    </div>
                </div>
            </div>
        @endif
    @empty
        <p style="font-size: 0.875rem; opacity: .7;">Todavía no hay mensajes. Usa <strong>Responder</strong> para escribirle al solicitante.</p>
    @endforelse
</div>
