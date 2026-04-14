<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketUrgency: string implements HasLabel
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
        };
    }
}
