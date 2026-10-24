<?php

namespace App\Filament\Soporte\Pages;

use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Informe de Mantenimientos Programados.
 *
 * Muestra KPIs, gráficos y drill-down de los mantenimientos en una
 * ventana configurable (30/90/180/365 días). Los datos respetan el
 * scope del usuario:
 *   - super_admin/admin: todos los mantenimientos.
 *   - supervisor_soporte: mantenimientos de assets de su depto.
 *   - agente/tecnico: solo los que se les asignaron.
 *
 * Permite exportar la vista completa a PDF (dompdf) usando el mismo
 * patrón que SlaReport.
 */
class MaintenancesReport extends Page
{
    protected string $view = 'filament.soporte.pages.maintenances-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Informe mantenimientos';

    protected static ?string $title = 'Informe de Mantenimientos Programados';

    protected static ?int $navigationSort = 46;

    /** Ventana en días — bindeada al select del blade. */
    #[Url]
    public int $window = 90;

    /** Filtros avanzados — todos opcionales y combinables. */
    #[Url]
    public ?string $managementArea = null;

    #[Url]
    public ?int $projectId = null;

    #[Url]
    public ?string $locationZone = null;

    #[Url]
    public ?string $fieldName = null;

    #[Url]
    public ?int $agentId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return static::$title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('Exportar a PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn () => $this->exportPdf()),
        ];
    }

    public function exportPdf(): StreamedResponse
    {
        $pdf = Pdf::loadView('pdfs.maintenances-report', $this->getViewData())
            ->setPaper('letter', 'portrait');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'informe-mantenimientos-'.now()->format('Y-m-d').'.pdf',
        );
    }

    /**
     * Devuelve el bundle completo de datos que consume el blade y el
     * PDF. Todos los cálculos van acá para que ambos vean lo mismo.
     *
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $from = now()->subDays($this->window)->startOfDay();
        $to = now()->endOfDay();

        $baseQuery = $this->scopedQuery()
            ->whereBetween('scheduled_at', [$from->toDateString(), $to->toDateString()]);

        // Filtros derivados del asset (gerencia, proyecto, zona, campo).
        $assetFilters = array_filter([
            'management_area' => $this->managementArea,
            'project_id' => $this->projectId,
            'location_zone' => $this->locationZone,
            'field' => $this->fieldName,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($assetFilters)) {
            $baseQuery->whereHas('asset', function ($q) use ($assetFilters): void {
                foreach ($assetFilters as $col => $val) {
                    $q->where($col, $val);
                }
            });
        }

        if ($this->agentId) {
            $baseQuery->where('agent_id', $this->agentId);
        }

        $items = (clone $baseQuery)
            ->with(['asset:id,asset_tag,type,hostname,management_area', 'agent:id,name'])
            ->get();

        // KPIs
        $total = $items->count();
        $cumplidos = $items->where('status', MaintenanceStatus::Cumplido)->count();
        $noCumplidos = $items->where('status', MaintenanceStatus::NoCumplido)->count();
        $pendientes = $items->where('status', MaintenanceStatus::Pendiente)->count();
        $vencidos = $items->filter(fn (ScheduledMaintenance $m) => $m->isOverdue())->count();

        $compliancePct = $total > 0
            ? (int) round(($cumplidos / $total) * 100)
            : null;

        // Distribución mensual (bar chart)
        $monthly = $items
            ->groupBy(fn (ScheduledMaintenance $m) => $m->scheduled_at->format('Y-m'))
            ->map(fn ($group) => [
                'total' => $group->count(),
                'cumplidos' => $group->where('status', MaintenanceStatus::Cumplido)->count(),
                'no_cumplidos' => $group->where('status', MaintenanceStatus::NoCumplido)->count(),
                'pendientes' => $group->where('status', MaintenanceStatus::Pendiente)->count(),
            ])
            ->sortKeys()
            ->all();

        // Top razones de no cumplimiento (horizontal bars). Se agrupan
        // por texto normalizado (lowercase + trim) para consolidar
        // "Usuario no disponible" y "usuario no disponible".
        $notCompletedReasons = $items
            ->where('status', MaintenanceStatus::NoCumplido)
            ->whereNotNull('not_completed_reason')
            ->groupBy(fn (ScheduledMaintenance $m) => trim(mb_strtolower($m->not_completed_reason)))
            ->map(fn ($group) => [
                'reason' => trim($group->first()->not_completed_reason),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->all();

        // Ranking por agente
        $byAgent = $items
            ->groupBy('agent_id')
            ->map(function ($group) {
                $total = $group->count();
                $cumplidos = $group->where('status', MaintenanceStatus::Cumplido)->count();
                $agent = $group->first()->agent;

                return [
                    'agent_id' => $group->first()->agent_id,
                    'agent_name' => $agent?->name ?? 'Sin agente',
                    'total' => $total,
                    'cumplidos' => $cumplidos,
                    'no_cumplidos' => $group->where('status', MaintenanceStatus::NoCumplido)->count(),
                    'pendientes' => $group->where('status', MaintenanceStatus::Pendiente)->count(),
                    'compliance_pct' => $total > 0 ? (int) round(($cumplidos / $total) * 100) : 0,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        // Tabla drill-down: no cumplidos con detalle
        $notCompletedList = $items
            ->where('status', MaintenanceStatus::NoCumplido)
            ->map(fn (ScheduledMaintenance $m) => [
                'id' => $m->id,
                'scheduled_at' => $m->scheduled_at->translatedFormat('d M Y'),
                'asset_tag' => $m->asset?->asset_tag ?? 'Sin TAG',
                'asset_type' => strtoupper($m->asset?->type ?? ''),
                'agent_name' => $m->agent?->name ?? '—',
                'reason' => $m->not_completed_reason ?? 'Sin motivo registrado',
                'closed_at' => $m->completed_at?->translatedFormat('d M Y H:i') ?? '—',
            ])
            ->values()
            ->all();

        // Distribución por gerencia (torta nueva)
        $byManagementArea = $items
            ->filter(fn (ScheduledMaintenance $m) => $m->asset?->management_area)
            ->groupBy(fn (ScheduledMaintenance $m) => $m->asset->management_area)
            ->map(fn ($group, $area) => [
                'label' => $area,
                'total' => $group->count(),
                'cumplidos' => $group->where('status', MaintenanceStatus::Cumplido)->count(),
                'compliance_pct' => $group->count() > 0
                    ? (int) round(($group->where('status', MaintenanceStatus::Cumplido)->count() / $group->count()) * 100)
                    : 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'window' => $this->window,
            'from' => $from,
            'to' => $to,
            'generated_at' => now(),
            'kpi' => [
                'total' => $total,
                'cumplidos' => $cumplidos,
                'no_cumplidos' => $noCumplidos,
                'pendientes' => $pendientes,
                'vencidos' => $vencidos,
                'compliance_pct' => $compliancePct,
            ],
            'monthly' => $monthly,
            'notCompletedReasons' => $notCompletedReasons,
            'byAgent' => $byAgent,
            'byManagementArea' => $byManagementArea,
            'notCompletedList' => $notCompletedList,
            'filterOptions' => $this->filterOptions(),
            'activeFilters' => array_filter([
                'Gerencia' => $this->managementArea,
                'Proyecto / Contrato' => $this->projectId
                    ? (string) Project::query()->whereKey($this->projectId)->value('code')
                    : null,
                'Zona' => $this->locationZone,
                'Campo' => $this->fieldName,
                'Agente' => $this->agentId
                    ? (string) User::query()->whereKey($this->agentId)->value('name')
                    : null,
            ]),
        ];
    }

    /**
     * Catálogos para poblar los selects de filtros. Derivados en vivo de
     * los activos existentes para no mostrar opciones vacías.
     *
     * @return array<string, array<int|string, string>>
     */
    protected function filterOptions(): array
    {
        return [
            'managementAreas' => Asset::query()
                ->whereNotNull('management_area')
                ->distinct()
                ->orderBy('management_area')
                ->pluck('management_area', 'management_area')
                ->all(),
            'projects' => Project::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->id => "{$p->code} · {$p->name}"])
                ->all(),
            'zones' => Asset::query()
                ->whereNotNull('location_zone')
                ->distinct()
                ->orderBy('location_zone')
                ->pluck('location_zone', 'location_zone')
                ->all(),
            'fields' => Asset::query()
                ->whereNotNull('field')
                ->distinct()
                ->orderBy('field')
                ->pluck('field', 'field')
                ->all(),
            'agents' => User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['agente_soporte', 'tecnico_campo']))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        ];
    }

    /**
     * Query base con el scope por rol aplicado — misma lógica que
     * el getEloquentQuery del resource.
     */
    protected function scopedQuery()
    {
        $query = ScheduledMaintenance::query();
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1=0');
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        if ($user instanceof User && $user->hasRole('supervisor_soporte') && $user->department_id) {
            return $query->whereHas('asset', fn ($q) => $q->where('department_id', $user->department_id));
        }

        return $query->where('agent_id', $user->id);
    }
}
