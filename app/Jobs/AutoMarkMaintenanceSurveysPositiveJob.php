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
        $surveys = MaintenanceSurvey::query()
            ->whereNull('responded_at')
            ->where('created_at', '<=', now()->subDay())
            ->get();

        $count = 0;

        foreach ($surveys as $survey) {
            $survey->forceFill([
                'rating' => 5,
                'responded_at' => now(),
                'comment' => trim((string) $survey->comment.' (auto-positiva: sin respuesta en 1 día)'),
            ])->save();
            $count++;
        }

        if ($count > 0) {
            Log::info("Auto-maintenance-CSAT: {$count} encuesta(s) marcadas como 5★.");
        }
    }
}
