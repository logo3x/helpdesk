<?php

namespace App\Jobs;

use App\Services\KbSuggestionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Revisión periódica de consultas del chatbot sin buena respuesta: la IA
 * decide qué temas ameritan un artículo KB y crea los borradores.
 */
class GenerateKbSuggestionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function handle(KbSuggestionService $service): void
    {
        if (! config('services.llm.kb_suggestions_enabled')) {
            return;
        }

        $created = $service->run();

        if ($created->isNotEmpty()) {
            Log::info('[KB-SUGERENCIAS] Borradores sugeridos creados', [
                'articles' => $created->pluck('title', 'id')->all(),
            ]);
        }
    }
}
