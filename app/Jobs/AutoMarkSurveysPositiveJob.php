<?php

namespace App\Jobs;

use App\Models\SatisfactionSurvey;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Si el solicitante no respondió la encuesta de satisfacción en
 * `config('tickets.csat_auto_positive_days')` días, se marca como
 * 5 estrellas automáticamente y se anota en `comment` que fue
 * auto-positiva (para distinguir en reportes vs. ratings reales).
 *
 * El silencio = aprobación es razonable porque el ticket llevaba
 * cerrado al menos esos días sin que el cliente se quejara.
 */
class AutoMarkSurveysPositiveJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        $days = (int) config('tickets.csat_auto_positive_days', 7);

        $surveys = SatisfactionSurvey::query()
            ->whereNull('responded_at')
            ->where('created_at', '<=', now()->subDays($days))
            ->get();

        $count = 0;

        foreach ($surveys as $survey) {
            $survey->forceFill([
                'rating' => 5,
                'responded_at' => now(),
                'comment' => trim((string) $survey->comment.' (auto-positiva: cliente no respondió en '.$days.' días)'),
            ])->save();
            $count++;
        }

        if ($count > 0) {
            Log::channel('stack')->info("Auto-CSAT: {$count} encuesta(s) marcadas como 5★ tras {$days} días sin respuesta.");
        }
    }
}
