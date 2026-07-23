<div class="space-y-4 py-2">
    {{-- Estrellas --}}
    <div class="flex items-center gap-1">
        @for ($i = 1; $i <= 5; $i++)
            @if ($i <= $survey->rating)
                <x-filament::icon icon="heroicon-s-star" class="h-8 w-8 text-amber-400" />
            @else
                <x-filament::icon icon="heroicon-o-star" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
            @endif
        @endfor
        <span class="ml-2 text-lg font-bold text-gray-800 dark:text-gray-200">{{ $survey->rating }}/5</span>
    </div>

    {{-- Etiqueta verbal --}}
    <div>
        <span @class([
            'inline-flex rounded-full px-3 py-1 text-sm font-medium',
            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' => $survey->rating <= 2,
            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $survey->rating === 3,
            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => $survey->rating >= 4,
        ])>
            {{ match ($survey->rating) {
                1 => 'Muy insatisfecho',
                2 => 'Insatisfecho',
                3 => 'Regular',
                4 => 'Satisfecho',
                5 => 'Muy satisfecho',
                default => 'Sin calificar',
            } }}
        </span>
    </div>

    {{-- Comentario --}}
    @if ($survey->comment)
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/50">
            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">Comentario del usuario</p>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $survey->comment }}</p>
        </div>
    @else
        <p class="text-sm text-gray-400 italic">Sin comentario adicional.</p>
    @endif

    {{-- Metadata --}}
    <div class="flex gap-6 text-xs text-gray-400">
        <span>Respondido: {{ $survey->responded_at?->translatedFormat('d/m/Y H:i') ?? '—' }}</span>
        <span>Ticket: {{ $ticket->number }}</span>
    </div>
</div>
