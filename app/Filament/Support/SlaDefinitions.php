<?php

namespace App\Filament\Support;

use App\Enums\TicketStatus;
use InvalidArgumentException;

/**
 * Única fuente de las definiciones de SLA que se muestran en los "?" de
 * ayuda: ficha del ticket (TicketViewInfolist), escritorio de soporte
 * (TicketResolutionMetricsWidget, SlaComplianceWidget, AgentRankingWidget)
 * y cualquier otra pantalla que hable de los mismos conceptos.
 *
 * Los plazos se leen de config/tickets.php para que el texto no quede
 * desactualizado cuando cambie la configuración.
 */
final class SlaDefinitions
{
    /** Porcentaje de cumplimiento a partir del cual el semáforo es verde. */
    public const COMPLIANCE_GOOD = 90;

    /** Porcentaje de cumplimiento a partir del cual el semáforo es ámbar. */
    public const COMPLIANCE_WARNING = 70;

    public const BUSINESS_HOURS = 'de lunes a viernes, 08:00-18:00';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $pending = TicketStatus::PendienteCliente->getLabel();
        $resolved = TicketStatus::Resuelto->getLabel();
        $closed = TicketStatus::Cerrado->getLabel();
        $autoCloseDays = (int) config('tickets.auto_close_days', 2);
        $daysLabel = $autoCloseDays === 1 ? '1 día' : "{$autoCloseDays} días";
        $reminderHours = implode(' y ', array_map(
            fn (int|string $hours): string => "{$hours} h",
            (array) config('tickets.awaiting_customer.reminder_hours', [3, 7]),
        ));
        $awaitingCloseHours = (int) config('tickets.awaiting_customer.close_hours', 10);
        $hours = self::BUSINESS_HOURS;
        $good = self::COMPLIANCE_GOOD;
        $warning = self::COMPLIANCE_WARNING;

        return [
            'created' => 'Fecha y hora en que el solicitante creó el ticket. Desde aquí empieza a correr el reloj del SLA.',

            'first_response' => 'Momento en que un agente respondió o tomó el ticket por primera vez. Detiene el reloj de primera respuesta del SLA.',

            'first_response_due' => "Plazo del SLA para la primera respuesta. Se calcula al crear el ticket en horas hábiles ({$hours}).",

            'resolution_due' => "Plazo del SLA para resolver. Se calcula al crear el ticket en horas hábiles ({$hours}). Si el ticket se marca como {$resolved} después de este plazo, queda fuera de SLA. El tiempo en {$pending} no cuenta.",

            'resolved' => "Momento en que el agente marcó el ticket como {$resolved}. Aquí se detiene el SLA y empieza a contar el tiempo {$resolved} → {$closed}.",

            'closed' => "El solicitante lo cierra al confirmar la solución. Si no responde, el sistema lo cierra solo {$daysLabel} después de marcarlo como {$resolved}. Un ticket en {$pending} sin respuesta se cierra a las {$awaitingCloseHours} h hábiles.",

            'paused' => "Horas hábiles acumuladas mientras el ticket estuvo en {$pending}. El SLA se pausa en ese estado porque la demora no depende del agente. Si el cliente no responde, recibe recordatorios a las {$reminderHours} hábiles y el ticket se cierra a las {$awaitingCloseHours} h.",

            'solution_time' => "Trabajo efectivo del agente: horas hábiles ({$hours}) entre la creación y la resolución, sin contar el tiempo en {$pending}. Termina al marcar {$resolved}: la espera hasta el cierre no cuenta.",

            'resolved_to_closed' => "Horas calendario entre {$resolved} y {$closed}: lo que tardó el solicitante en confirmar o la espera hasta el cierre automático ({$daysLabel}). No suma al tiempo de solución ni al SLA, porque el SLA se detiene al resolver.",

            'first_response_breached' => 'Rojo: se venció el plazo de primera respuesta. Verde: se cumplió o aún está dentro del plazo.',

            'resolution_breached' => 'Rojo: se venció el plazo de resolución. Verde: se cumplió o aún está dentro del plazo.',

            'sla_compliance' => "Porcentaje de tickets resueltos dentro del plazo de resolución del SLA. Verde: {$good} % o más; ámbar: {$warning} % o más; rojo: menos de {$warning} %.",

            'first_response_avg' => 'Tiempo promedio entre la creación del ticket y la primera respuesta de un agente. Se mide en tiempo calendario (incluye noches y fines de semana).',

            'resolution_avg' => "Tiempo promedio entre la creación y la resolución, sin contar el tiempo en {$pending}. Se mide en tiempo calendario.",

            'csat' => 'Promedio de las encuestas de satisfacción respondidas (de 1 a 5). Incluye las auto-positivas, que cuentan como 5.',

            'reopened_rate' => 'Porcentaje de tickets resueltos por el agente que el solicitante volvió a abrir. Mientras más bajo, mejor.',

            'impact' => 'Alcance del problema: cuántos usuarios o servicios afecta.',

            'urgency' => 'Qué tan rápido debe resolverse desde la perspectiva del solicitante.',

            'status' => "Etapa actual del ticket. En {$pending} el reloj del SLA queda pausado.",
        ];
    }

    public static function help(string $key): string
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("No hay definición de SLA para [{$key}].");
    }

    /**
     * Color del semáforo de cumplimiento, igual en todos los widgets.
     */
    public static function complianceColor(int|float|null $percentage): string
    {
        return match (true) {
            $percentage === null => 'gray',
            $percentage >= self::COMPLIANCE_GOOD => 'success',
            $percentage >= self::COMPLIANCE_WARNING => 'warning',
            default => 'danger',
        };
    }
}
