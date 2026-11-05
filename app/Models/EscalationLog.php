<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'type',
        'sla_minutes',
        'elapsed_minutes',
        'notified_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sla_minutes' => 'integer',
            'elapsed_minutes' => 'integer',
        ];
    }

    /**
     * Si el registro corresponde a un incumplimiento (100% del SLA)
     * y no a una alerta preventiva (70% / 90%).
     */
    public function isBreach(): bool
    {
        return str_ends_with((string) $this->type, '_breach');
    }

    /**
     * Nivel de la alerta para leer su configuración en
     * config('tickets.sla_alerts.channels'): warning_70, warning_90 o breach.
     */
    public function alertLevel(): string
    {
        if ($this->isBreach()) {
            return 'breach';
        }

        return str_starts_with((string) $this->type, 'warning_90_') ? 'warning_90' : 'warning_70';
    }

    /**
     * Umbral del SLA que disparó el registro: 70, 90 o 100.
     */
    public function thresholdPercent(): int
    {
        return match ($this->alertLevel()) {
            'breach' => 100,
            'warning_90' => 90,
            default => 70,
        };
    }

    /**
     * Qué reloj SLA generó el registro: primera respuesta o resolución.
     */
    public function metricLabel(): string
    {
        return str_contains((string) $this->type, 'first_response') ? 'Primera respuesta' : 'Resolución';
    }

    /**
     * Etiqueta en español del tipo, p. ej. "Incumplido" o "Alerta 90%".
     */
    public function typeLabel(): string
    {
        if ($this->isBreach()) {
            return 'Incumplido';
        }

        if (preg_match('/^warning_(\d+)_/', (string) $this->type, $matches)) {
            return "Alerta {$matches[1]}%";
        }

        return ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    /**
     * Porcentaje del SLA consumido al momento del registro.
     */
    public function consumedPercent(): ?int
    {
        return $this->sla_minutes > 0
            ? (int) round($this->elapsed_minutes / $this->sla_minutes * 100)
            : null;
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function notifiedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }
}
