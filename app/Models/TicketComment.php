<?php

namespace App\Models;

use App\Models\Concerns\AuditsActivity;
use App\Services\TicketAttachmentService;
use Database\Factories\TicketCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;

class TicketComment extends Model implements HasMedia
{
    /** @use HasFactory<TicketCommentFactory> */
    use AuditsActivity, HasFactory, InteractsWithMedia, SoftDeletes;

    /** Módulo con el que aparece en la bitácora. */
    protected string $auditLogName = 'tickets';

    /**
     * Adjuntos en disco privado: solo se sirven por la ruta autenticada
     * tickets.attachments.show. Los antiguos pueden seguir en "public"
     * (ver tickets:move-attachments-private).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(TicketAttachmentService::COLLECTION)
            ->useDisk(TicketAttachmentService::DISK)
            ->acceptsFile(fn (File $file): bool => TicketAttachmentService::isAcceptable($file));
    }

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_private',
        'is_system_event',
        'event_type',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_system_event' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_private', false);
    }
}
