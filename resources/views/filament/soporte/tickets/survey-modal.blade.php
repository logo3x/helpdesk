<div class="space-y-4 py-2">
    @if ($survey->isPending())
        {{-- Encuesta pendiente --}}
        <div class="flex flex-col items-center gap-3 py-4 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40">
                <x-filament::icon icon="heroicon-o-clock" class="h-7 w-7 text-amber-500" />
            </div>
            <div>
                <p class="text-base font-semibold text-gray-800 dark:text-gray-200">Encuesta pendiente de respuesta</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    El usuario aún no ha respondido la encuesta de satisfacción.<br>
                    La encuesta fue enviada al cerrar el ticket.
                </p>
            </div>
            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                Ticket: {{ $ticket->number }}
            </span>
        </div>
    @else
        @php
            $dims = \App\Models\SatisfactionSurvey::DIMENSIONS;
            $avg = $survey->averageRating() ?? $survey->rating ?? 0;
        @endphp

        {{-- Tabla de dimensiones --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="py-2 pl-3 pr-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Dimensión</th>
                        @foreach([1,2,3,4,5] as $n)
                            <th class="px-3 py-2 text-center text-xs font-bold text-gray-600 dark:text-gray-300">{{ $n }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($dims as $field => $label)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="py-2 pl-3 pr-2 text-xs text-gray-700 dark:text-gray-300">{{ $label }}</td>
                            @foreach([1,2,3,4,5] as $n)
                                <td class="px-3 py-2 text-center">
                                    @if(($survey->{$field} ?? 0) >= $n)
                                        <span class="text-amber-400 text-base leading-none">★</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600 text-base leading-none">★</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-800 font-semibold border-t border-gray-200 dark:border-gray-700">
                        <td class="py-2 pl-3 pr-2 text-xs text-gray-700 dark:text-gray-300">Promedio general</td>
                        <td colspan="5" class="px-3 py-2 text-center text-sm font-bold
                            @if($avg >= 4) text-green-600 dark:text-green-400
                            @elseif($avg >= 3) text-amber-600 dark:text-amber-400
                            @else text-red-600 dark:text-red-400
                            @endif">
                            {{ number_format($avg, 2) }} / 5
                            — @if($avg >= 4) Satisfactorio @elseif($avg >= 3) Regular @else Insatisfactorio @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
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
    @endif
</div>
