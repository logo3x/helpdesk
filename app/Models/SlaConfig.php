<?php

namespace App\Models;

use App\Enums\TicketPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Find the matching SLA config for a given department + priority.
     */
    public static function findFor(?int $departmentId, TicketPriority $priority): ?self
    {
        if ($departmentId === null) {
            return null;
        }

        return static::query()
            ->where('department_id', $departmentId)
            ->where('priority', $priority)
            ->where('is_active', true)
            ->first();
    }
}
