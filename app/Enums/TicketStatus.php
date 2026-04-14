<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Nuevo = 'nuevo';
    case Asignado = 'asignado';
    case EnProgreso = 'en_progreso';
    case PendienteCliente = 'pendiente_cliente';
    case Resuelto = 'resuelto';
    case Cerrado = 'cerrado';
    case Reabierto = 'reabierto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::Asignado => 'Asignado',
            self::EnProgreso => 'En progreso',
            self::PendienteCliente => 'Pendiente cliente',
            self::Resuelto => 'Resuelto',
            self::Cerrado => 'Cerrado',
            self::Reabierto => 'Reabierto',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Nuevo => 'info',
            self::Asignado => 'primary',
            self::EnProgreso => 'warning',
            self::PendienteCliente => 'gray',
            self::Resuelto => 'success',
            self::Cerrado => 'gray',
            self::Reabierto => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [
            self::Nuevo,
            self::Asignado,
            self::EnProgreso,
            self::PendienteCliente,
            self::Reabierto,
        ], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }
}
