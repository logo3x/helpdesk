<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\MaintenanceSurvey;
use App\Notifications\AssetMaintenanceSurveyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMaintenanceSurveyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Asset $asset,
    ) {}

    public function handle(): void
    {
        $custodian = $this->asset->user;

        if (! $custodian) {
            return;
        }

        // Un solo survey pendiente por activo+usuario a la vez
        $existing = MaintenanceSurvey::query()
            ->where('asset_id', $this->asset->id)
            ->where('user_id', $custodian->id)
            ->whereNull('responded_at')
            ->exists();

        if ($existing) {
            return;
        }

        $survey = MaintenanceSurvey::create([
            'asset_id' => $this->asset->id,
            'user_id' => $custodian->id,
        ]);

        $custodian->notify(new AssetMaintenanceSurveyNotification($this->asset, $survey));
    }
}
