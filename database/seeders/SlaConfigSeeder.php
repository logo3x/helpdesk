<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\SlaConfig;
use Illuminate\Database\Seeder;

class SlaConfigSeeder extends Seeder
{
    /**
     * Default SLA times (business minutes) per priority.
     * Applied to all departments. Adjust per-department via admin panel.
     *
     * @var array<string, array{first_response: int, resolution: int}>
     */
    protected array $defaults = [
        'critica' => ['first_response' => 30, 'resolution' => 240],      // 30 min / 4 hrs
        'alta' => ['first_response' => 60, 'resolution' => 480],          // 1 hr / 8 hrs (1 day)
        'media' => ['first_response' => 120, 'resolution' => 1200],       // 2 hrs / 20 hrs (2 days)
        'baja' => ['first_response' => 240, 'resolution' => 2400],        // 4 hrs / 40 hrs (4 days)
        'planificada' => ['first_response' => 480, 'resolution' => 6000], // 8 hrs / 100 hrs (10 days)
    ];

    public function run(): void
    {
        $departments = Department::where('is_active', true)->get();

        foreach ($departments as $department) {
            foreach ($this->defaults as $priorityValue => $times) {
                SlaConfig::updateOrCreate(
                    [
                        'department_id' => $department->id,
                        'priority' => $priorityValue,
                    ],
                    [
                        'first_response_minutes' => $times['first_response'],
                        'resolution_minutes' => $times['resolution'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
