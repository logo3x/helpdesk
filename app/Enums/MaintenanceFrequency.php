<?php

namespace App\Enums;

/**
 * Frecuencias oficiales de mantenimiento en Confipetrol.
 *
 * Solo dos valores porque el negocio maneja únicamente ciclos
 * cuatrimestrales (120 días) para equipos con uso intensivo y anuales
 * (365 días) para equipos administrativos.
 */
enum MaintenanceFrequency: string
{
    case Cuatrimestral = 'cuatrimestral';
    case Anual = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::Cuatrimestral => 'Cuatrimestral (cada 120 días)',
            self::Anual => 'Anual (cada 365 días)',
        };
    }

    public function days(): int
    {
        return match ($this) {
            self::Cuatrimestral => 120,
            self::Anual => 365,
        };
    }
}
