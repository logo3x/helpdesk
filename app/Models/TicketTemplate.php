<?php

namespace App\Models;

use App\Enums\TicketImpact;
use App\Enums\TicketUrgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'description',
        'category_id',
        'impact',
        'urgency',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'impact' => TicketImpact::class,
            'urgency' => TicketUrgency::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
