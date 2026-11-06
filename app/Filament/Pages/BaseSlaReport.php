<?php

namespace App\Filament\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Concerns\HasSlaBreachDetails;
use App\Models\Department;
use App\Models\Ticket;
use App\Services\ConsolidadoIndicadoresExporter;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Implementación única del reporte de cumplimiento SLA, compartida por
 * los paneles /admin y /soporte.
 *
 * Vista del cumplimiento de SLA por departamento y prioridad,
 * complementada con secciones operativas:
 *  - "Tickets en riesgo": no resueltos cuya fecha límite de resolución
 *    está vencida o vence en las próximas 24h (mira hacia adelante).
 *  - "Tickets con SLA incumplido" y "Últimas escalaciones" (ver
 *    HasSlaBreachDetails).
 *
 * Soporta una ventana de tiempo configurable (7/30/90/365 días) o un
 * rango personalizado.
 *
 * Cada panel extiende esta clase y solo define navegación, acceso, la
 * ruta de "ver ticket" (ticketViewRouteName) y, si aplica, el alcance
 * por departamento (departmentScopeId). El alcance se aplica a TODAS
 * las consultas: departamentos, matriz, resumen, en riesgo,
 * incumplidos, escalaciones, políticas y exportaciones.
 */
abstract class BaseSlaReport extends Page
{
    use HasSlaBreachDetails;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

    /**
     * Ventana de tiempo del reporte en días. Bindeada a un <select> en
     * la vista con wire:model.live para que el reporte se recalcule
     * sin recargar la página. Usado cuando dateFrom/dateTo están vacíos.
     */
    public string $window = '30';

    /**
     * Rango personalizado (formato Y-m-d). Cuando ambos tienen valor,
     * se ignora $window y se filtra por [dateFrom, dateTo].
     */
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    /**
     * Departamento al que se limita el reporte. Null = todos los
     * departamentos. Cualquier otro valor (incluido 0, que no coincide
     * con ningún departamento) restringe todas las consultas.
     */
    protected function departmentScopeId(): ?int
    {
        return null;
    }

    protected function isDepartmentScoped(): bool
    {
        return $this->departmentScopeId() !== null;
    }

    public function getViewData(): array
    {
        [$from, $to, $labelDays] = $this->resolveRange();
        $scopeDepartmentId = $this->departmentScopeId();

        $departments = Department::query()
            ->where('is_active', true)
            ->when($scopeDepartmentId !== null, fn (Builder $query) => $query->whereKey($scopeDepartmentId))
            ->orderBy('name')
            ->get();

        $priorities = TicketPriority::cases();

        $atRisk = $this->atRiskTickets();
        $breached = $this->breachedTickets($from, $to, $scopeDepartmentId);
        $escalations = $this->escalationsInRange($from, $to, $scopeDepartmentId);

        return [
            'window' => $labelDays,
            'fromDate' => $from,
            'toDate' => $to,
            'isCustomRange' => $this->hasCustomRange(),
            'report' => $this->buildMatrix($departments, $priorities, $from, $to),
            'priorities' => $priorities,
            'escalations' => $this->controlEscalationTable($escalations),
            'atRisk' => $this->controlRiskTable($atRisk),
            'summary' => $this->summary($from, $to),
            'breachedTickets' => $this->controlBreachTable($breached),
            'tableTotals' => ['risk' => $atRisk->count(), 'breach' => $breached->count(), 'escalations' => $escalations->count()],
            'tableOptions' => $this->tableFilterOptions($departments, $atRisk, $breached, $escalations),
            'ticketRoute' => $this->ticketViewRouteName(),
            'slaPolicies' => $this->slaPolicyMatrix($departments),
        ];
    }

    /**
     * Aplica un preset rápido de rango. Limpia cualquier rango
     * personalizado previo.
     */
    public function applyPreset(string $days): void
    {
        $this->window = $days;
        $this->dateFrom = null;
        $this->dateTo = null;
    }

    /**
     * Limpia el rango personalizado y vuelve al preset.
     */
    public function clearCustomRange(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
    }

    protected function hasCustomRange(): bool
    {
        return ! empty($this->dateFrom) && ! empty($this->dateTo);
    }

    /**
     * Devuelve el rango efectivo del reporte. Si hay rango personalizado
     * válido se usa ese (cruzando las fechas si vienen invertidas); si
     * no, se calcula desde now()->subDays($window). `labelDays` solo se
     * usa para las etiquetas de la vista.
     *
     * Date::parse respeta el proxy CarbonImmutable configurado en
     * AppServiceProvider.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface, 2: int}
     */
    protected function resolveRange(): array
    {
        if ($this->hasCustomRange()) {
            try {
                $from = Date::parse($this->dateFrom)->startOfDay();
                $to = Date::parse($this->dateTo)->endOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
                }
                $labelDays = (int) $from->diffInDays($to) + 1;

                return [$from, $to, $labelDays];
            } catch (\Throwable) {
                // Fecha inválida: cae al preset.
            }
        }

        $days = max(1, (int) $this->window);
        $from = now()->subDays($days)->startOfDay();
        $to = now()->endOfDay();

        return [$from, $to, $days];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Exportar a PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => $this->exportPdf()),

            Action::make('exportConsolidado')
                ->label('Exportar Consolidado (Excel)')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->tooltip(fn (): string => $this->isDepartmentScoped()
                    ? 'Descarga el "Consolidado de Indicadores TI" con la plantilla oficial del Grupo Protexa, solo con los datos de tu departamento.'
                    : 'Descarga el "Consolidado de Indicadores TI" con la plantilla oficial del Grupo Protexa.')
                ->visible(fn (): bool => $this->departmentScopeId() !== 0)
                ->action(fn () => $this->exportConsolidado()),
        ];
    }

    /**
     * Exporta a PDF lo mismo que se ve en pantalla (mismo alcance,
     * filtros y orden).
     */
    public function exportPdf(): StreamedResponse
    {
        $pdf = Pdf::loadView('pdfs.sla-report', $this->getViewData())
            ->setPaper('letter', 'landscape');

        $filename = 'reporte-sla-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Descarga el consolidado anual con el mismo alcance del reporte:
     * todos los departamentos, o solo el del supervisor. Un supervisor
     * sin departamento (alcance 0) no puede descargarlo.
     *
     * El año se toma del rango personalizado si existe; si no, el año en
     * curso (los presets miran hacia atrás desde hoy).
     */
    public function exportConsolidado(): StreamedResponse
    {
        abort_if($this->departmentScopeId() === 0, 403);

        $year = $this->hasCustomRange() && $this->dateFrom
            ? (int) Date::parse($this->dateFrom)->format('Y')
            : (int) now()->format('Y');

        $scopeDepartmentId = $this->departmentScopeId();
        $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary($year, $scopeDepartmentId);
        $filename = $scopeDepartmentId === null
            ? "consolidado-indicadores-ti-{$year}.xlsx"
            : "consolidado-indicadores-ti-{$year}-depto-{$scopeDepartmentId}.xlsx";

        return response()->streamDownload(
            fn () => print ($binary),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    /**
     * Limita una consulta de tickets al departamento del alcance, si lo hay.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected function scopeToDepartment(Builder $query): Builder
    {
        $scopeDepartmentId = $this->departmentScopeId();

        return $query->when($scopeDepartmentId !== null, fn (Builder $q) => $q->where('department_id', $scopeDepartmentId));
    }

    /**
     * Resumen global de la ventana: total resueltos, breached, compliance %.
     *
     * @return array{resolved: int, breached: int, compliance: ?float}
     */
    protected function summary(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = $this->scopeToDepartment(
            Ticket::query()
                ->whereNotNull('sla_config_id')
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$from, $to])
        );

        $resolved = (clone $base)->count();
        $breached = (clone $base)->where('resolution_breached', true)->count();
        $compliance = $resolved > 0 ? round((($resolved - $breached) / $resolved) * 100, 1) : null;

        return [
            'resolved' => $resolved,
            'breached' => $breached,
            'compliance' => $compliance,
        ];
    }

    /**
     * Matriz de cumplimiento departamento × prioridad para la ventana
     * indicada. Los departamentos ya vienen limitados por el alcance.
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<int, TicketPriority>  $priorities
     * @return array<int, array{department: string, department_id: int, priorities: array<int, array{label: string, value: string, total: int, breached: int, compliance: ?float}>}>
     */
    protected function buildMatrix(Collection $departments, array $priorities, CarbonInterface $from, CarbonInterface $to): array
    {
        $report = [];

        foreach ($departments as $dept) {
            $row = ['department' => $dept->name, 'department_id' => $dept->id, 'priorities' => []];

            foreach ($priorities as $priority) {
                $query = Ticket::query()
                    ->where('department_id', $dept->id)
                    ->where('priority', $priority)
                    ->whereNotNull('sla_config_id')
                    ->whereNotNull('resolved_at')
                    ->whereBetween('resolved_at', [$from, $to]);

                $total = (clone $query)->count();
                $breached = (clone $query)->where('resolution_breached', true)->count();
                $compliance = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : null;

                $row['priorities'][] = [
                    'label' => $priority->getLabel(),
                    'value' => $priority->value,
                    'total' => $total,
                    'breached' => $breached,
                    'compliance' => $compliance,
                ];
            }

            $report[] = $row;
        }

        return $report;
    }

    /**
     * Tickets NO resueltos cuya fecha límite ya pasó o vence en las
     * próximas 24h. Ordenados por urgencia (los ya vencidos primero).
     *
     * @return \Illuminate\Support\Collection<int, array{ticket: Ticket, hours_left: float, is_breached: bool}>
     */
    protected function atRiskTickets(): \Illuminate\Support\Collection
    {
        $openStatuses = [
            TicketStatus::Nuevo,
            TicketStatus::Asignado,
            TicketStatus::EnProgreso,
            TicketStatus::PendienteCliente,
            TicketStatus::Reabierto,
        ];

        $threshold = now()->addHours(24);

        $tickets = $this->scopeToDepartment(
            Ticket::query()
                ->whereNotNull('sla_config_id')
                ->whereNotNull('resolution_due_at')
                ->whereIn('status', $openStatuses)
                ->where('resolution_due_at', '<=', $threshold)
                ->with('department:id,name', 'requester:id,name', 'assignee:id,name')
                ->orderBy('resolution_due_at')
                ->limit(25)
        )->get();

        return $tickets->map(function (Ticket $t) {
            $diff = now()->diffInMinutes($t->resolution_due_at, false);

            return [
                'ticket' => $t,
                'hours_left' => round($diff / 60, 1),
                'is_breached' => $diff < 0,
            ];
        });
    }
}
