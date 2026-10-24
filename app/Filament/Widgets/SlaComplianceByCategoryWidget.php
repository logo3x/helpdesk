<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Ticket;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * SLA compliance discriminado por categoría y departamento — últimos 30 días.
 *
 * El reporte SLA global vive en /admin/sla-report. Este widget lo desgaja
 * en el dashboard: ¿qué categoría de qué departamento está incumpliendo?
 * Semáforo verde/ámbar/rojo. Las categorías hija aparecen agrupadas bajo
 * su padre con el nombre indentado.
 */
class SlaComplianceByCategoryWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 50;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->buildQuery())
            ->heading('SLA compliance por categoría (últimos 30 días)')
            ->description('Porcentaje de tickets resueltos dentro del SLA, agrupado por departamento y categoría.')
            ->paginated([10, 25, 50])
            ->defaultSort('compliance_pct', 'asc')
            ->columns([
                TextColumn::make('department_name')
                    ->label('Departamento')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Categoría')
                    ->sortable(),
                TextColumn::make('resolved_count')
                    ->label('Resueltos')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('breached_count')
                    ->label('Incumplidos')
                    ->alignEnd()
                    ->color(fn ($state) => (int) $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('compliance_pct')
                    ->label('Compliance')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : $state.'%')
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->weight('bold'),
            ]);
    }

    /**
     * Construye una query en memoria con las categorías + sus métricas de SLA.
     * Como no es una query Eloquent "normal" (hay cálculos derivados),
     * exponemos la colección como builder con una tabla temporal in-memory
     * via Collection+toQuery no es viable; usamos la ruta de query builder
     * sobre categories con selectSub.
     *
     * @return Builder<Category>
     */
    protected function buildQuery(): Builder
    {
        $since = now()->subDays(30);

        return Category::query()
            ->leftJoin('departments', 'departments.id', '=', 'categories.department_id')
            ->leftJoin('categories as parent_cat', 'parent_cat.id', '=', 'categories.parent_id')
            ->select([
                'categories.*',
                'departments.name as department_name',
                'parent_cat.name as parent_name',
            ])
            ->selectRaw('(CASE WHEN categories.parent_id IS NULL THEN categories.name ELSE CONCAT(parent_cat.name, " › ", categories.name) END) as full_name')
            ->selectSub(
                Ticket::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tickets.category_id', 'categories.id')
                    ->whereNotNull('tickets.resolved_at')
                    ->where('tickets.resolved_at', '>=', $since)
                    ->toBase(),
                'resolved_count'
            )
            ->selectSub(
                Ticket::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tickets.category_id', 'categories.id')
                    ->whereNotNull('tickets.resolved_at')
                    ->where('tickets.resolved_at', '>=', $since)
                    ->where('tickets.resolution_breached', true)
                    ->toBase(),
                'breached_count'
            )
            ->selectSub(
                Ticket::query()
                    ->selectRaw('CASE WHEN COUNT(*) = 0 THEN NULL ELSE ROUND(100 * (COUNT(*) - SUM(CASE WHEN tickets.resolution_breached = 1 THEN 1 ELSE 0 END)) / COUNT(*), 0) END')
                    ->whereColumn('tickets.category_id', 'categories.id')
                    ->whereNotNull('tickets.resolved_at')
                    ->where('tickets.resolved_at', '>=', $since)
                    ->toBase(),
                'compliance_pct'
            )
            ->where('categories.is_active', true);
    }

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['super_admin', 'admin']);
    }
}
