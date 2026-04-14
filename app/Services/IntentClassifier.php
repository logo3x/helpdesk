<?php

namespace App\Services;

use App\Models\ChatFlow;
use Illuminate\Support\Collection;

/**
 * Simple keyword-based intent classifier.
 * Checks the user message against ChatFlow triggers and returns
 * the best matching flow (if any) with a confidence score.
 *
 * In Fase 5 this will be augmented with embeddings for semantic matching.
 */
class IntentClassifier
{
    /**
     * @return array{flow: ChatFlow|null, confidence: float}
     */
    public function classify(string $message): array
    {
        $message = mb_strtolower(trim($message));

        /** @var Collection<int, ChatFlow> $flows */
        $flows = ChatFlow::active()->orderBy('sort_order')->get();

        $bestFlow = null;
        $bestScore = 0.0;

        foreach ($flows as $flow) {
            $score = $this->scoreFlow($flow, $message);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestFlow = $flow;
            }
        }

        return [
            'flow' => $bestScore >= 0.3 ? $bestFlow : null,
            'confidence' => round($bestScore, 2),
        ];
    }

    /**
     * Score how well a flow matches the message (0.0–1.0).
     * Simple: count of trigger keywords found / total triggers.
     */
    protected function scoreFlow(ChatFlow $flow, string $message): float
    {
        $triggers = $flow->triggers;

        if (empty($triggers)) {
            return 0.0;
        }

        $matches = 0;

        foreach ($triggers as $trigger) {
            if (str_contains($message, mb_strtolower($trigger))) {
                $matches++;
            }
        }

        return $matches / count($triggers);
    }
}
