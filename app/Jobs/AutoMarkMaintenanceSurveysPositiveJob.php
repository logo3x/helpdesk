<?php

namespace App\Jobs;

use App\Models\MaintenanceSurvey;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AutoMarkMaintenanceSurveysPositiveJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        $days = (int) config('tickets.maintenance_csat_auto_positive_days', 1);

        $surveys = MaintenanceSurvey::query()
            ->whereNull('responded_at')
            ->where('created_at', '<=', now()->subDays($days))
            ->get();

        $count = 0;

        foreach ($surveys as $survey) {
            $survey->forceFill([
                'rating' => 5,
                'responded_at' => now(),
                'comment' => trim((string) $survey->comment." (auto-positiva: sin respuesta en {$days} día(s))"),
            ])->save();
            $count++;
        }

        if ($count > 0) {
            Log::info("Auto-maintenance-CSAT: {$count} encuesta(s) marcadas como 5★ tras {$days} día(s) sin respuesta.");
        }
    }
}
