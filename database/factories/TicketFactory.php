<?php

namespace Database\Factories;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $impact = fake()->randomElement(TicketImpact::cases());
        $urgency = fake()->randomElement(TicketUrgency::cases());

        return [
            'number' => sprintf('TK-%d-%05d', now()->year, fake()->unique()->numberBetween(1, 99999)),
            'subject' => Str::limit(fake()->sentence(), 120, ''),
            'description' => fake()->paragraphs(2, true),
            'status' => TicketStatus::Nuevo,
            'priority' => TicketPriority::fromMatrix($impact, $urgency),
            'impact' => $impact,
            'urgency' => $urgency,
            'requester_id' => User::factory(),
            'assigned_to_id' => null,
            'department_id' => Department::factory(),
            'category_id' => Category::factory(),
        ];
    }

    public function assigned(?User $assignee = null): self
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Asignado,
            'assigned_to_id' => $assignee?->id ?? User::factory(),
        ]);
    }

    public function resolved(): self
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Resuelto,
            'resolved_at' => now()->subHours(2),
            'first_responded_at' => now()->subHours(3),
        ]);
    }
}
