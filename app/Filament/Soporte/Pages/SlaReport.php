<?php

namespace App\Filament\Soporte\Pages;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Department;
use App\Models\EscalationLog;
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
 * Reporte de cumplimiento SLA en el panel /soporte.
 *
 * - super_admin / admin → ven todos los departamentos.
 * - supervisor_soporte  → solo su propio departamento (scope automático
 *                          en cada query).
 * - agente / técnico    → no acceden.
 *
 * Reutiliza la vista Blade `filament.pages.sla-report` que ya tiene
 * gráficos, KPI cards con delta vs periodo anterior, sección de
 * tickets en riesgo, matriz dept×prioridad y escalaciones recientes.
 * Por eso esta page necesita exponer las MISMAS variables que el
 * Admin SlaReport: window, summary, report, atRisk, priorities,
 * escalations.
 */
class SlaReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

    /**
     * Ventana de tiempo del reporte en días (binding del <select>
     * en la vista, con wire:model.live). Usado cuando dateFrom/dateTo
     * están vacíos.
     */
    public string $window = '30';

    /**
     * Rango personalizado (formato Y-m-d). Cuando ambos tienen valor,
     * se ignora $window y se filtra por [dateFrom, dateTo].
     */
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function getViewData(): array
    {
        [$fromDate, $toDate, $labelDays] = $this->resolveRange();

        // Scope: admin/super_admin ven todos, supervisor solo su depto.
        $user = auth()->user();
        $isAdmin = $user?->hasAnyRole(['super_admin', 'admin']) ?? false;

        $departmentsQuery = Department::query()->where('is_active', true);
        if (! $isAdmin && $user?->department_id) {
            $departmentsQuery->where('id', $user->department_id);
        }
        $departments = $departmentsQuery->orderBy('name')->get();

        $priorities = TicketPriority::cases();

        return [
            'window' => $labelDays,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'isCustomRange' => $this->hasCustomRange(),
            'report' => $this->buildMatrix($departments, $priorities, $fromDate, $toDate),
            'priorities' => $priorities,
            'escalations' => $this->latestEscalations($isAdmin, $user?->department_id),
            'atRisk' => $this->atRiskTickets($isAdmin, $user?->department_id),
            'summary' => $this->summary($fromDate, $toDate, $isAdmin, $user?->department_id),
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
     * Devuelve el rango efectivo del reporte:
     *   [CarbonInterface $from, CarbonInterface $to, int $labelDays]
     *
     * - Si el usuario definió dateFrom + dateTo, se usan esos.
     * - Si no, se calcula desde now()->subDays($window).
     *
     * `labelDays` es solo para mostrar en la vista (etiquetas y textos
     * que decían "últimos X días").
     */
    protected function resolveRange(): array
    {
        if ($this->hasCustomRange()) {
            try {
                // Uso Date::parse (proxy CarbonImmutable configurado en
                // AppServiceProvider) para que el tipo sea compatible con
                // el resto de fechas del proyecto.
                $from = Date::parse($this->dateFrom)->startOfDay();
                $to = Date::parse($this->dateTo)->endOfDay();
                if ($from->gt($to)) {
                    // Si invirtieron las fechas, las cruzamos silenciosamente.
                    [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
                }
                $labelDays = (int) $from->diffInDays($to) + 1;

                return [$from, $to, $labelDays];
            } catch (\Throwable) {
                // Fecha inválida → cae al preset.
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
                ->tooltip('Descarga el "Consolidado de Indicadores TI" con la plantilla oficial del Grupo Protexa.')
                ->action(fn () => $this->exportConsolidado()),
        ];
    }

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

    public function exportConsolidado(): StreamedResponse
    {
        // El año del reporte se determina por el rango si es custom,
        // o por el año actual si es preset. Los presets miran hacia
        // atrás desde hoy, así que el año en curso es la elección
        // natural.
        $year = $this->hasCustomRange() && $this->dateFrom
            ? (int) Date::parse($this->dateFrom)->format('Y')
            : (int) now()->format('Y');

        $binary = app(ConsolidadoIndicadoresExporter::class)->toBinary($year);
        $filename = "consolidado-indicadores-ti-{$year}.xlsx";

        return response()->streamDownload(
            fn () => print ($binary),
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    /**
     * Aplica scope por departamento a una query base de tickets
     * cuando el usuario no es admin.
     */
    protected function scopeToDepartment(Builder $query, bool $isAdmin, ?int $deptId): Builder
    {
        if (! $isAdmin && $deptId) {
            $query->where('department_id', $deptId);
        }

        return $query;
    }

    /**
     * Resumen global de la ventana: resueltos, breached, compliance %.
     *
     * @return array{resolved: int, breached: int, compliance: ?float}
     */
    protected function summary(CarbonInterface $from, CarbonInterface $to, bool $isAdmin, ?int $deptId): array
    {
        $base = $this->scopeToDepartment(
            Ticket::query()
                ->whereNotNull('sla_config_id')
                ->whereNotNull('resolved_at')
                ->whereBetween('resolved_at', [$from, $to]),
            $isAdmin,
            $deptId,
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
     * Matriz de cumplimiento departamento × prioridad. Los depts ya
     * vienen filtrados desde getViewData() según el rol.
     *
     * @param  Collection<int, Department>  $departments
     * @param  array<int, TicketPriority>  $priorities
     * @return array<int, array{department: string, priorities: array<int, array{label: string, total: int, breached: int, compliance: ?float}>}>
     */
    protected function buildMatrix(Collection $departments, array $priorities, CarbonInterface $from, CarbonInterface $to): array
    {
        $report = [];

        foreach ($departments as $dept) {
            $row = ['department' => $dept->name, 'priorities' => []];

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
     * Tickets en riesgo (no resueltos cuya fecha límite vence en 24h
     * o ya venció), respetando el scope por depto del supervisor.
     *
     * @return \Illuminate\Support\Collection<int, array{ticket: Ticket, hours_left: float, is_breached: bool}>
     */
    protected function atRiskTickets(bool $isAdmin, ?int $deptId): \Illuminate\Support\Collection
    {
        $openStatuses = [
            TicketStatus::Nuevo,
            TicketStatus::Asignado,
            TicketStatus::EnProgreso,
            TicketStatus::PendienteCliente,
            TicketStatus::Reabierto,
        ];

        $threshold = now()->addHours(24);

        $query = Ticket::query()
            ->whereNotNull('sla_config_id')
            ->whereNotNull('resolution_due_at')
            ->whereIn('status', $openStatuses)
            ->where('resolution_due_at', '<=', $threshold)
            ->with('department:id,name', 'requester:id,name', 'assignee:id,name')
            ->orderBy('resolution_due_at')
            ->limit(25);

        $tickets = $this->scopeToDepartment($query, $isAdmin, $deptId)->get();

        return $tickets->map(function (Ticket $t) {
            $diff = now()->diffInMinutes($t->resolution_due_at, false);

            return [
                'ticket' => $t,
                'hours_left' => round($diff / 60, 1),
                'is_breached' => $diff < 0,
            ];
        });
    }

    /**
     * Últimas escalaciones, filtradas al depto del supervisor si aplica.
     *
     * @return Collection<int, EscalationLog>
     */
    protected function latestEscalations(bool $isAdmin, ?int $deptId): Collection
    {
        $query = EscalationLog::with('ticket:id,number,subject,department_id', 'notifiedUser:id,name')
            ->latest()
            ->limit(20);

        if (! $isAdmin && $deptId) {
            $query->whereHas('ticket', fn ($q) => $q->where('department_id', $deptId));
        }

        return $query->get();
    }
}
