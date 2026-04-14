<?php

namespace App\Models;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, InteractsWithMedia, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'impact', 'urgency', 'assigned_to_id', 'department_id', 'category_id'])
            ->logOnlyDirty()
            ->useLogName('tickets')
            ->setDescriptionForEvent(fn (string $eventName): string => "Ticket {$this->number} fue {$eventName}");
    }

    protected $fillable = [
        'number',
        'subject',
        'description',
        'status',
        'priority',
        'impact',
        'urgency',
        'requester_id',
        'assigned_to_id',
        'department_id',
        'category_id',
        'sla_config_id',
        'first_responded_at',
        'resolved_at',
        'closed_at',
        'reopened_at',
        'first_response_due_at',
        'resolution_due_at',
        'first_response_breached',
        'resolution_breached',
        'paused_minutes',
        'paused_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'impact' => TicketImpact::class,
            'urgency' => TicketUrgency::class,
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_breached' => 'boolean',
            'resolution_breached' => 'boolean',
            'paused_minutes' => 'integer',
            'paused_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<SlaConfig, $this> */
    public function slaConfig(): BelongsTo
    {
        return $this->belongsTo(SlaConfig::class);
    }

    /** @return HasMany<EscalationLog, $this> */
    public function escalationLogs(): HasMany
    {
        return $this->hasMany(EscalationLog::class);
    }

    /** @return HasMany<TicketComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /** @return HasMany<TicketComment, $this> */
    public function publicComments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->where('is_private', false);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            TicketStatus::Nuevo,
            TicketStatus::Asignado,
            TicketStatus::EnProgreso,
            TicketStatus::PendienteCliente,
            TicketStatus::Reabierto,
        ]);
    }
}
