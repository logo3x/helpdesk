<?php

namespace App\Filament\Pages;

use App\Enums\TicketPriority;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\Ticket;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class SlaReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reporte SLA';

    protected static ?string $title = 'Reporte de cumplimiento SLA';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.sla-report';

    public function getViewData(): array
    {
        $departments = Department::where('is_active', true)->get();
        $priorities = TicketPriority::cases();

        $report = [];

        foreach ($departments as $dept) {
            $row = ['department' => $dept->name, 'priorities' => []];

            foreach ($priorities as $priority) {
                $query = Ticket::query()
                    ->where('department_id', $dept->id)
                    ->where('priority', $priority)
                    ->whereNotNull('sla_config_id')
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '>=', now()->subDays(30));

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

        $recentEscalations = EscalationLog::with('ticket:id,number,subject', 'notifiedUser:id,name')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'report' => $report,
            'priorities' => $priorities,
            'escalations' => $recentEscalations,
        ];
    }
}
