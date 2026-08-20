<?php

namespace Database\Factories;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledMaintenance>
 */
class ScheduledMaintenanceFactory extends Factory
{
    protected $model = ScheduledMaintenance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'agent_id' => User::factory(),
            'created_by_id' => User::factory(),
            'scheduled_at' => now()->addDays(30),
            'status' => MaintenanceStatus::Pendiente,
            'progress_percent' => 0,
            'frequency' => MaintenanceFrequency::Cuatrimestral,
        ];
    }

    public function completed(): self
    {
        return $this->state([
            'status' => MaintenanceStatus::Cumplido,
            'progress_percent' => 100,
            'completed_at' => now(),
            'observations' => $this->faker->sentence(),
        ]);
    }

    public function notCompleted(?string $reason = null): self
    {
        return $this->state([
            'status' => MaintenanceStatus::NoCumplido,
            'progress_percent' => 40,
            'completed_at' => now(),
            'not_completed_reason' => $reason ?? $this->faker->sentence(),
        ]);
    }

    public function overdue(): self
    {
        return $this->state([
            'scheduled_at' => now()->subDays(10),
            'status' => MaintenanceStatus::Pendiente,
        ]);
    }
}
