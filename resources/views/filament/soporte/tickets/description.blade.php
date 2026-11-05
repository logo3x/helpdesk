@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();
    $text = (string) $ticket->description;
    $markdown = fn (?string $value) => str((string) $value)->markdown([
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
    ])->toHtmlString();

    // Tickets escalados desde el asistente: encabezado con datos de la
    // sesión + transcripción. Se separan para no mostrar un muro de texto.
    $fromAssistant = str_starts_with(ltrim($text), '# Ticket escalado desde el asistente virtual');
    $header = $text;
    $transcript = null;
    $fields = [];

    if ($fromAssistant) {
        [$header, $transcript] = array_pad(explode('## Transcripción de la conversación', $text, 2), 2, null);

        preg_match_all('/\*\*([^*]+):\*\*\s*(.+)/u', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $fields[trim($match[1])] = trim($match[2]);
        }
    }

    $summary = $fields['Resumen del usuario'] ?? null;
    $aiPriority = $fields['Prioridad sugerida por la IA'] ?? null;
    $details = collect($fields)->only(['Departamento destino', 'Duración', 'Total mensajes', 'Sesión de chat'])->filter();
    $messages = $transcript ? substr_count($transcript, '---') + 1 : 0;

    // Estilos en línea: los paneles Filament no incluyen utilidades Tailwind.
    $card = 'border: 1px solid rgba(128,128,128,.25); border-radius: 0.75rem; background: rgba(128,128,128,.04);';
    $chip = 'display: inline-flex; flex-direction: column; padding: 0.375rem 0.75rem; border-radius: 0.5rem; background: rgba(128,128,128,.08); font-size: 0.75rem;';
@endphp

<div style="display: flex; flex-direction: column; gap: 0.875rem;">
    {{-- Quién, cuándo y por dónde llegó --}}
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; font-size: 0.8125rem; opacity: .85;">
        <span>👤 <strong>{{ $ticket->requester?->name ?? '—' }}</strong></span>
        <span>🕒 {{ $ticket->created_at->format('d/m/Y H:i') }} ({{ $ticket->created_at->diffForHumans() }})</span>
        <span style="padding: 0.1rem 0.6rem; border-radius: 9999px; font-weight: 600; background: {{ $fromAssistant ? 'rgba(139,92,246,.15)' : 'rgba(14,165,233,.15)' }};">
            {{ $fromAssistant ? '🤖 Escalado desde el asistente virtual' : '🖥️ Creado en el portal' }}
        </span>
    </div>

    @if ($fromAssistant)
        @if ($summary)
            <div style="{{ $card }} padding: 1rem 1.25rem; border-left: 4px solid rgba(139,92,246,.7);">
                <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; opacity: .65; margin-bottom: 0.25rem;">Problema reportado</div>
                <div style="font-size: 1rem; font-weight: 600;">{{ $summary }}</div>
            </div>
        @endif

        @if ($details->isNotEmpty() || $aiPriority)
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                @foreach ($details as $label => $value)
                    <span style="{{ $chip }}">
                        <span style="opacity: .65;">{{ $label }}</span>
                        <strong>{{ $value }}</strong>
                    </span>
                @endforeach
                @if ($aiPriority)
                    <span style="{{ $chip }} background: rgba(245,158,11,.12);">
                        <span style="opacity: .65;">Prioridad sugerida por la IA</span>
                        <strong>{{ $aiPriority }}</strong>
                    </span>
                @endif
            </div>
        @endif

        @if ($transcript)
            <details style="{{ $card }} padding: 0.75rem 1rem;">
                <summary style="cursor: pointer; font-weight: 600; font-size: 0.875rem;">
                    💬 Ver conversación con el asistente ({{ $messages }} {{ $messages === 1 ? 'mensaje' : 'mensajes' }})
                </summary>
                <div class="fi-prose" style="max-width: none; margin-top: 0.75rem; font-size: 0.875rem; overflow-wrap: anywhere;">
                    {!! $markdown($transcript) !!}
                </div>
            </details>
        @endif
    @else
        <div class="fi-prose" style="{{ $card }} max-width: none; padding: 1rem 1.25rem; font-size: 0.9375rem; line-height: 1.6; overflow-wrap: anywhere;">
            {!! $markdown($text) !!}
        </div>
    @endif
</div>
