<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Models\SlaConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaConfig>
 */
class SlaConfigFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'priority' => TicketPriority::Media,
            'first_response_minutes' => 30,
            'resolution_minutes' => 480,
            'is_active' => true,
        ];
    }
}
