<?php

namespace App\Jobs;

use App\Services\SlaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckSlaBreachesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300; // 5 minutes

    public function handle(SlaService $sla): void
    {
        $escalations = $sla->checkBreaches();

        if ($escalations > 0) {
            Log::channel('stack')->info("SLA check: {$escalations} escalation(s) created.");
        }
    }
}
