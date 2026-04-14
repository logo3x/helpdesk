<?php

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;

describe('Impact x Urgency matrix', function () {
    it('maps low impact to low-end priorities', function () {
        expect(TicketPriority::fromMatrix(TicketImpact::Bajo, TicketUrgency::Baja))
            ->toBe(TicketPriority::Planificada);
        expect(TicketPriority::fromMatrix(TicketImpact::Bajo, TicketUrgency::Media))
            ->toBe(TicketPriority::Baja);
        expect(TicketPriority::fromMatrix(TicketImpact::Bajo, TicketUrgency::Alta))
            ->toBe(TicketPriority::Media);
    });

    it('maps medium impact to mid-range priorities', function () {
        expect(TicketPriority::fromMatrix(TicketImpact::Medio, TicketUrgency::Baja))
            ->toBe(TicketPriority::Baja);
        expect(TicketPriority::fromMatrix(TicketImpact::Medio, TicketUrgency::Media))
            ->toBe(TicketPriority::Media);
        expect(TicketPriority::fromMatrix(TicketImpact::Medio, TicketUrgency::Alta))
            ->toBe(TicketPriority::Alta);
    });

    it('maps high impact to high-end priorities', function () {
        expect(TicketPriority::fromMatrix(TicketImpact::Alto, TicketUrgency::Baja))
            ->toBe(TicketPriority::Media);
        expect(TicketPriority::fromMatrix(TicketImpact::Alto, TicketUrgency::Media))
            ->toBe(TicketPriority::Alta);
        expect(TicketPriority::fromMatrix(TicketImpact::Alto, TicketUrgency::Alta))
            ->toBe(TicketPriority::Critica);
    });
});
