<?php

namespace App\Enums;

/**
 * Estados posibles de un mantenimiento programado (ScheduledMaintenance).
 */
enum MaintenanceStatus: string
{
    case Pendiente = 'pendiente';
    case Cumplido = 'cumplido';
    case NoCumplido = 'no_cumplido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Cumplido => 'Cumplido',
            self::NoCumplido => 'No cumplido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::Cumplido => 'success',
            self::NoCumplido => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return $this !== self::Pendiente;
    }
}
