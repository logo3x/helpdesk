<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exporta el "Consolidado de Indicadores TI" en el formato oficial
 * que solicita el Grupo Protexa.
 *
 * Estrategia: lee el archivo plantilla oficial desde
 * storage/app/templates/consolidado-indicadores.xlsx (conservando
 * colores, fórmulas VLOOKUP y estilos) y solo escribe los valores
 * de las celdas mensuales que el sistema puede calcular hoy:
 *
 *   1. % Tickets Resueltos (C14..C25)
 *   2. Meta (D14..D25) — fija en 0.95
 *   3. Grado de Satisfacción Soporte (C28..C39) — CSAT ponderado
 *   4. Grado de Satisfacción Aplicaciones (D28..D39) — vacío hoy
 *   5. Meta CSAT (E28..E39) — fija en 0.95
 *   6. Up-time (C44..C55) — 100 - % breach del mes
 *   7. Meta Up-time (D44..D55) — fija en 95
 *   8. Cantidad de incidencias mayores (C78..C89) — Críticas resueltas
 *
 * Las secciones que el sistema NO mide (Obsolescencia, Proyectos,
 * Ciberseguridad, Finanzas, Hechos Relevantes) se dejan como están
 * en la plantilla — el usuario las completa manualmente.
 */
class ConsolidadoIndicadoresExporter
{
    protected const META_TICKETS = 0.95;

    protected const META_CSAT = 0.95;

    protected const META_UPTIME = 95;

    /**
     * Mapeo mes → fila para cada sección. Los índices 1-12
     * corresponden a los meses enero-diciembre.
     *
     * @var array<int, array{tickets: int, csat: int, uptime: int, incidencias: int}>
     */
    protected const ROW_MAP = [
        1 => ['tickets' => 14, 'csat' => 28, 'uptime' => 44, 'incidencias' => 78],
        2 => ['tickets' => 15, 'csat' => 29, 'uptime' => 45, 'incidencias' => 79],
        3 => ['tickets' => 16, 'csat' => 30, 'uptime' => 46, 'incidencias' => 80],
        4 => ['tickets' => 17, 'csat' => 31, 'uptime' => 47, 'incidencias' => 81],
        5 => ['tickets' => 18, 'csat' => 32, 'uptime' => 48, 'incidencias' => 82],
        6 => ['tickets' => 19, 'csat' => 33, 'uptime' => 49, 'incidencias' => 83],
        7 => ['tickets' => 20, 'csat' => 34, 'uptime' => 50, 'incidencias' => 84],
        8 => ['tickets' => 21, 'csat' => 35, 'uptime' => 51, 'incidencias' => 85],
        9 => ['tickets' => 22, 'csat' => 36, 'uptime' => 52, 'incidencias' => 86],
        10 => ['tickets' => 23, 'csat' => 37, 'uptime' => 53, 'incidencias' => 87],
        11 => ['tickets' => 24, 'csat' => 38, 'uptime' => 54, 'incidencias' => 88],
        12 => ['tickets' => 25, 'csat' => 39, 'uptime' => 55, 'incidencias' => 89],
    ];

    /**
     * Ruta relativa (dentro de storage/app) al archivo plantilla.
     */
    protected function templatePath(): string
    {
        return storage_path('app/templates/consolidado-indicadores.xlsx');
    }

    /**
     * Genera el .xlsx llenando solo los meses del año indicado y
     * devuelve el binario listo para descargar.
     */
    public function toBinary(int $year): string
    {
        $sp = $this->loadTemplate();
        $sheet = $sp->getSheet(0);

        // Mes actual limita hasta dónde escribimos — no rellenamos
        // meses futuros con ceros, se dejan vacíos como en la plantilla.
        $currentMonth = (int) now()->format('n');
        $currentYear = (int) now()->format('Y');
        $maxMonth = $year === $currentYear ? $currentMonth : 12;

        for ($month = 1; $month <= $maxMonth; $month++) {
            $rows = self::ROW_MAP[$month];
            $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->endOfMonth();

            // 1. % Tickets Resueltos + Meta
            $ticketsRate = $this->ticketsResolvedRate($monthStart, $monthEnd);
            if ($ticketsRate !== null) {
                $sheet->setCellValue("C{$rows['tickets']}", $ticketsRate);
            }
            $sheet->setCellValue("D{$rows['tickets']}", self::META_TICKETS);

            // 2. Grado de Satisfacción (Soporte) + Meta
            $csat = $this->supportCsatRate($monthStart, $monthEnd);
            if ($csat !== null) {
                $sheet->setCellValue("C{$rows['csat']}", $csat);
            }
            $sheet->setCellValue("E{$rows['csat']}", self::META_CSAT);

            // 3. Up-time (% tickets sin breach de resolución)
            $uptime = $this->uptimeRate($monthStart, $monthEnd);
            if ($uptime !== null) {
                $sheet->setCellValue("C{$rows['uptime']}", $uptime);
            }
            $sheet->setCellValue("D{$rows['uptime']}", self::META_UPTIME);

            // 4. Incidencias mayores (críticas resueltas en el mes)
            $incidencias = $this->majorIncidents($monthStart, $monthEnd);
            $sheet->setCellValue("C{$rows['incidencias']}", $incidencias);
        }

        // Cabecera del año
        $sheet->setCellValue('B6', "Consolidado de Indicadores {$year} | Tecnologías de la Información");

        $writer = new Xlsx($sp);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    protected function loadTemplate(): Spreadsheet
    {
        return IOFactory::load($this->templatePath());
    }

    /**
     * Tickets resueltos en el mes / tickets creados en el mes,
     * como fracción 0-1. Null si no hubo tickets creados.
     */
    protected function ticketsResolvedRate(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $created = Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        if ($created === 0) {
            return null;
        }

        $resolved = Ticket::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('resolved_at')
            ->count();

        return round($resolved / $created, 4);
    }

    /**
     * Grado de satisfacción CSAT promedio (0-1) sobre encuestas
     * respondidas en el mes.
     */
    protected function supportCsatRate(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $avg = SatisfactionSurvey::query()
            ->whereNotNull('responded_at')
            ->whereBetween('responded_at', [$from, $to])
            ->whereNotNull('rating')
            ->avg('rating');

        if ($avg === null) {
            return null;
        }

        // Rating es 1-5, lo llevamos a fracción sobre 5.
        return round(((float) $avg) / 5, 4);
    }

    /**
     * "Up-time" del helpdesk: % de tickets resueltos sin breach
     * de resolución en el mes (entero, no fracción — así lo tiene
     * la plantilla: 87, 88, 96...).
     */
    protected function uptimeRate(CarbonImmutable $from, CarbonImmutable $to): ?int
    {
        $resolved = Ticket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->whereNotNull('sla_config_id')
            ->count();

        if ($resolved === 0) {
            return null;
        }

        $breached = Ticket::query()
            ->whereBetween('resolved_at', [$from, $to])
            ->whereNotNull('sla_config_id')
            ->where('resolution_breached', true)
            ->count();

        return (int) round((($resolved - $breached) / $resolved) * 100);
    }

    /**
     * Incidencias mayores del mes: tickets de prioridad Crítica
     * cerrados dentro del mes.
     */
    protected function majorIncidents(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Ticket::query()
            ->where('priority', TicketPriority::Critica)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }
}
