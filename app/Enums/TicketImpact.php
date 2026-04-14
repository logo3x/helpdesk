<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketImpact: string implements HasLabel
{
    case Bajo = 'bajo';
    case Medio = 'medio';
    case Alto = 'alto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bajo => 'Bajo',
            self::Medio => 'Medio',
            self::Alto => 'Alto',
        };
    }
}
