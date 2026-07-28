<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    /** @var array<string, string> */
    public const DIMENSIONS = [
        'rating_attention' => 'Atención general',
        'rating_contact' => 'Facilidad de contacto',
        'rating_resolution' => 'Resolución de tu incidente/solicitud',
        'rating_time' => 'Tiempo de solución',
        'rating_knowledge' => 'Conocimiento técnico del responsable focal',
        'rating_attitude' => 'Amabilidad y disposición del responsable focal',
    ];

    protected $fillable = [
        'ticket_id',
        'user_id',
        'rating',
        'rating_attention',
        'rating_contact',
        'rating_resolution',
        'rating_time',
        'rating_knowledge',
        'rating_attitude',
        'comment',
        'token',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rating_attention' => 'integer',
            'rating_contact' => 'integer',
            'rating_resolution' => 'integer',
            'rating_time' => 'integer',
            'rating_knowledge' => 'integer',
            'rating_attitude' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    /** Average of all dimension ratings (null if not responded). */
    public function averageRating(): ?float
    {
        if ($this->isPending()) {
            return null;
        }

        $values = array_filter(
            array_map(fn ($key) => $this->{$key}, array_keys(self::DIMENSIONS)),
            fn ($v) => $v !== null,
        );

        return count($values) > 0 ? round(array_sum($values) / count($values), 2) : null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $survey) {
            if (blank($survey->token)) {
                $survey->token = Str::random(64);
            }
        });
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

    public function isPending(): bool
    {
        return $this->responded_at === null;
    }
}
