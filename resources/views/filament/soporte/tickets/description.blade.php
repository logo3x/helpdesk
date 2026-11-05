@php
    /** @var \App\Models\Ticket $ticket */
    $ticket = $getRecord();

    // Tickets escalados desde el asistente: encabezado con datos de la
    // sesión + transcripción. Se separan para no mostrar un muro de texto.
    $parsed = \App\Services\TicketDescriptionParser::parse($ticket->description);
    $fromAssistant = $parsed['fromAssistant'];
    $chip = 'inline-flex flex-col rounded-lg px-3 py-1.5 text-xs';
@endphp

<div class="flex flex-col gap-3.5">
    {{-- Quién, cuándo y por dónde llegó --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[0.8125rem] text-gray-700 dark:text-gray-300">
        <span>👤 <strong>{{ $ticket->requester?->name ?? '—' }}</strong></span>
        <span>🕒 {{ $ticket->created_at->format('d/m/Y H:i') }} ({{ $ticket->created_at->diffForHumans() }})</span>
        <span @class([
            'rounded-full px-2.5 py-0.5 font-semibold',
            'bg-violet-500/15 text-violet-700 dark:text-violet-300' => $fromAssistant,
            'bg-sky-500/15 text-sky-700 dark:text-sky-300' => ! $fromAssistant,
        ])>
            {{ $fromAssistant ? '🤖 Escalado desde el asistente virtual' : '🖥️ Creado en el portal' }}
        </span>
    </div>

    @if ($fromAssistant)
        @if ($parsed['summary'])
            <div class="rounded-xl border border-l-4 border-gray-950/10 border-l-violet-500/70 bg-gray-950/[0.02] px-5 py-4 dark:border-white/10 dark:border-l-violet-400/70 dark:bg-white/[0.03]">
                <div class="mb-1 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Problema reportado</div>
                <div class="text-base font-semibold text-gray-950 dark:text-white">{{ $parsed['summary'] }}</div>
            </div>
        @endif

        @if ($parsed['details'] || $parsed['aiPriority'])
            <div class="flex flex-wrap gap-2">
                @foreach ($parsed['details'] as $label => $value)
                    <span class="{{ $chip }} bg-gray-950/5 dark:bg-white/5">
                        <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                        <strong>{{ $value }}</strong>
                    </span>
                @endforeach
                @if ($parsed['aiPriority'])
                    <span class="{{ $chip }} bg-amber-500/10 dark:bg-amber-400/10">
                        <span class="text-gray-500 dark:text-gray-400">Prioridad sugerida por la IA</span>
                        <strong>{{ $parsed['aiPriority'] }}</strong>
                    </span>
                @endif
            </div>
        @endif

        @if ($parsed['transcript'])
            <details class="rounded-xl border border-gray-950/10 bg-gray-950/[0.02] px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                <summary class="cursor-pointer text-sm font-semibold">
                    💬 Ver conversación con el asistente ({{ $parsed['messages'] }} {{ $parsed['messages'] === 1 ? 'mensaje' : 'mensajes' }})
                </summary>
                <div class="fi-prose mt-3 max-w-none text-sm [overflow-wrap:anywhere]">
                    {!! \App\Services\MarkdownRenderer::safe($parsed['transcript']) !!}
                </div>
            </details>
        @endif
    @else
        <div class="fi-prose max-w-none rounded-xl border border-gray-950/10 bg-gray-950/[0.02] px-5 py-4 text-[0.9375rem] leading-relaxed [overflow-wrap:anywhere] dark:border-white/10 dark:bg-white/[0.03]">
            {!! \App\Services\MarkdownRenderer::safe($parsed['body']) !!}
        </div>
    @endif
</div>
