<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasLabel
{
    case Planificada = 'planificada';
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Critica = 'critica';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planificada => 'Planificada',
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Critica => 'Crítica',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Planificada => 'gray',
            self::Baja => 'info',
            self::Media => 'warning',
            self::Alta => 'danger',
            self::Critica => 'danger',
        };
    }

    /**
     * ITIL Impact × Urgency priority matrix.
     *
     *                 urgency
     *                 ─────────────────────────────
     * impact          baja       media      alta
     * ─────────────   ────       ─────      ─────
     * bajo            planif.    baja       media
     * medio           baja       media      alta
     * alto            media      alta       crítica
     */
    public static function fromMatrix(TicketImpact $impact, TicketUrgency $urgency): self
    {
        return match ([$impact, $urgency]) {
            [TicketImpact::Bajo, TicketUrgency::Baja] => self::Planificada,
            [TicketImpact::Bajo, TicketUrgency::Media] => self::Baja,
            [TicketImpact::Bajo, TicketUrgency::Alta] => self::Media,
            [TicketImpact::Medio, TicketUrgency::Baja] => self::Baja,
            [TicketImpact::Medio, TicketUrgency::Media] => self::Media,
            [TicketImpact::Medio, TicketUrgency::Alta] => self::Alta,
            [TicketImpact::Alto, TicketUrgency::Baja] => self::Media,
            [TicketImpact::Alto, TicketUrgency::Media] => self::Alta,
            [TicketImpact::Alto, TicketUrgency::Alta] => self::Critica,
        };
    }
}
