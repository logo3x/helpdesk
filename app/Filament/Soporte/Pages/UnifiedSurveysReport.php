<?php

namespace App\Filament\Soporte\Pages;

use App\Models\Department;
use App\Models\MaintenanceSurvey;
use App\Models\SatisfactionSurvey;
use App\Models\Ticket;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte unificado de encuestas de satisfacción.
 *
 * Muestra en una sola tabla las encuestas de tickets (`satisfaction_surveys`)
 * y las encuestas de mantenimientos (`maintenance_surveys`), con un badge
 * de origen y filtro para discriminar.
 *
 * Antes era imposible saber de un vistazo si una calificación 2 estrellas
 * era por un ticket mal resuelto o por un mantenimiento mal hecho; esta
 * página lo deja claro.
 */
class UnifiedSurveysReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Encuestas (todas)';

    protected static ?string $title = 'Encuestas de satisfacción — unificadas';

    protected static ?int $navigationSort = 52;

    protected string $view = 'filament.soporte.pages.unified-surveys-report';

    /** Filtro de origen: 'all' | 'ticket' | 'maintenance'. */
    #[Url]
    public string $source = 'all';

    /** Filtro de rango en días. */
    #[Url]
    public string $window = '90';

    /** Filtro de rating mínimo (1-5). Vacío = sin filtro. */
    #[Url]
    public string $minRating = '';

    /** Filtro de departamento (solo admin; el supervisor queda fijo en el suyo). */
    #[Url]
    public string $departmentId = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']);
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
                ->color('gray')
                ->action(fn () => $this->exportPdf()),
        ];
    }

    public function exportPdf(): StreamedResponse
    {
        $data = $this->getViewData();
        $data['generatedAt'] = now();
        $data['sourceLabel'] = $data['sourceOptions'][$this->source] ?? 'Todas';
        $data['windowLabel'] = $data['windowOptions'][$this->window] ?? ($this->window.' días');
        $data['minRatingLabel'] = $this->minRating !== '' ? ($this->minRating.'★ o más') : 'Cualquiera';
        $data['departmentLabel'] = $data['departmentOptions'][$this->effectiveDepartmentId() ?? ''] ?? 'Todos';

        $pdf = Pdf::loadView('pdfs.unified-surveys-report', $data)
            ->setPaper('letter', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'encuestas-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function getViewData(): array
    {
        $rows = $this->buildRows();

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($rows),
            'sourceOptions' => [
                'all' => 'Todas las encuestas',
                'ticket' => 'Solo tickets',
                'maintenance' => 'Solo mantenimientos',
            ],
            'departmentOptions' => $this->departmentOptions(),
            'canChooseDepartment' => $this->departmentScope() === null,
            'windowOptions' => [
                '30' => '30 días',
                '90' => '90 días',
                '180' => '180 días',
                '365' => '1 año',
                '0' => 'Sin límite',
            ],
        ];
    }

    /**
     * Construye las filas combinadas, ya ordenadas y filtradas.
     *
     * @return Collection<int, array{
     *     id: int,
     *     source: string,
     *     related_label: string,
     *     related_url: ?string,
     *     user_name: ?string,
     *     rating: ?int,
     *     comment: ?string,
     *     responded_at: ?Carbon,
     *     created_at: ?Carbon,
     * }>
     */
    /**
     * Scope por departamento según rol:
     *   - super_admin / admin → null (ve TODO).
     *   - supervisor_soporte  → su propio department_id (ve solo lo suyo).
     *   - cualquier otro con canAccess()=false ya está bloqueado arriba.
     */
    /**
     * Departamento a filtrar: el del supervisor (fijo) o el elegido por
     * un admin en el filtro. Null = todos.
     */
    protected function effectiveDepartmentId(): ?int
    {
        return $this->departmentScope() ?? ($this->departmentId !== '' ? (int) $this->departmentId : null);
    }

    /**
     * @return array<int|string, string>
     */
    protected function departmentOptions(): array
    {
        $scope = $this->departmentScope();

        return Department::query()
            ->when($scope !== null, fn ($q) => $q->whereKey($scope))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function departmentScope(): ?int
    {
        $user = auth()->user();

        if (! $user || $user->hasAnyRole(['super_admin', 'admin'])) {
            return null;
        }

        return $user->department_id ?: -1; // -1 fuerza que no vea nada si no tiene depto
    }

    protected function buildRows(): Collection
    {
        $windowDays = (int) $this->window;
        $minRating = $this->minRating !== '' ? (int) $this->minRating : null;

        $rows = collect();

        if ($this->source === 'all' || $this->source === 'ticket') {
            $rows = $rows->merge($this->ticketRows($windowDays, $minRating));
        }

        if ($this->source === 'all' || $this->source === 'maintenance') {
            $rows = $rows->merge($this->maintenanceRows($windowDays, $minRating));
        }

        return $rows
            ->sortByDesc(fn (array $r) => $r['responded_at']?->timestamp ?? $r['created_at']?->timestamp ?? 0)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function ticketRows(int $windowDays, ?int $minRating): Collection
    {
        $scope = $this->effectiveDepartmentId();

        $query = SatisfactionSurvey::query()
            ->with(['ticket:id,number,subject,department_id', 'ticket.department:id,name', 'user:id,name'])
            ->when($windowDays > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($windowDays)))
            ->when($minRating !== null, fn ($q) => $q->where('rating', '>=', $minRating))
            ->when($scope !== null, fn ($q) => $q->whereHas('ticket', fn ($t) => $t->where('department_id', $scope)));

        return $query->get()->map(function (SatisfactionSurvey $s): array {
            return [
                'id' => $s->id,
                'source' => 'ticket',
                'related_label' => $s->ticket?->number
                    ? $s->ticket->number.' · '.mb_strimwidth((string) $s->ticket?->subject, 0, 60, '…')
                    : '— ticket eliminado —',
                'related_url' => $s->ticket
                    ? route('filament.soporte.resources.tickets.view', ['record' => $s->ticket->id])
                    : null,
                'department' => $s->ticket?->department?->name,
                'user_name' => $s->user?->name,
                'rating' => $s->rating,
                'comment' => $s->comment,
                'responded_at' => $s->responded_at,
                'created_at' => $s->created_at,
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function maintenanceRows(int $windowDays, ?int $minRating): Collection
    {
        $scope = $this->effectiveDepartmentId();

        $query = MaintenanceSurvey::query()
            ->with(['asset:id,asset_tag,hostname,type,department_id', 'asset.department:id,name', 'user:id,name'])
            ->when($windowDays > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($windowDays)))
            ->when($minRating !== null, fn ($q) => $q->where('rating', '>=', $minRating))
            ->when($scope !== null, fn ($q) => $q->whereHas('asset', fn ($a) => $a->where('department_id', $scope)));

        return $query->get()->map(function (MaintenanceSurvey $s): array {
            $asset = $s->asset;
            $label = $asset
                ? ($asset->asset_tag ?: $asset->hostname ?: 'Activo #'.$asset->id)
                : '— activo eliminado —';

            return [
                'id' => $s->id,
                'source' => 'maintenance',
                'related_label' => $label,
                'related_url' => $asset
                    ? route('filament.soporte.resources.assets.edit', ['record' => $asset->id])
                    : null,
                'department' => $asset?->department?->name,
                'user_name' => $s->user?->name,
                'rating' => $s->rating,
                'comment' => $s->comment,
                'responded_at' => $s->responded_at,
                'created_at' => $s->created_at,
            ];
        });
    }

    /**
     * Resumen de la ventana filtrada.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total: int, answered: int, pending: int, avg: ?float, byTicket: int, byMaintenance: int}
     */
    protected function buildStats(Collection $rows): array
    {
        $answered = $rows->filter(fn (array $r) => $r['rating'] !== null);
        $total = $rows->count();
        $byTicket = $rows->where('source', 'ticket')->count();
        $byMaintenance = $rows->where('source', 'maintenance')->count();

        return [
            'total' => $total,
            'answered' => $answered->count(),
            'pending' => $total - $answered->count(),
            'avg' => $answered->isNotEmpty() ? round($answered->avg('rating'), 2) : null,
            'byTicket' => $byTicket,
            'byMaintenance' => $byMaintenance,
        ];
    }
}
