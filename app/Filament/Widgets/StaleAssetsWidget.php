<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget que lista los equipos del inventario que no reportan scan
 * en los últimos 30 días. Útil para detectar:
 *  - Equipos perdidos / no devueltos.
 *  - Tareas programadas del agente PowerShell que dejaron de correr.
 *  - Equipos retirados que olvidaron marcar como tal.
 */
class StaleAssetsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return 'Equipos sin scan reciente (>30 días)';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->staleAssetsQuery())
            ->emptyStateHeading('Sin novedad')
            ->emptyStateDescription('Todos los equipos activos reportaron scan en los últimos 30 días.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('hostname')
                    ->label('Hostname')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('asset_tag')
                    ->label('Etiqueta')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sin asignar')
                    ->searchable(),

                TextColumn::make('department.name')
                    ->label('Depto')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('last_scan_at')
                    ->label('Último scan')
                    ->dateTime('d M Y')
                    ->placeholder('Nunca')
                    ->since()
                    ->color('warning'),

                TextColumn::make('agent_version')
                    ->label('Agente')
                    ->badge()
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        str_starts_with($state, '2.') => 'success',
                        default => 'warning',
                    })
                    ->placeholder('—')
                    ->tooltip(fn (?string $state) => $state === null
                        ? 'Sin agente PowerShell o nunca reportó'
                        : "Versión {$state} del agente"),

                TextColumn::make('last_scan_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ok' => 'success',
                        'partial' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('os_name')
                    ->label('SO')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordUrl(fn (Asset $record) => AssetResource::getUrl('edit', ['record' => $record]))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * @return Builder<Asset>
     */
    protected function staleAssetsQuery(): Builder
    {
        $threshold = Carbon::now()->subDays(30);

        return Asset::query()
            ->active()
            ->where(function (Builder $q) use ($threshold) {
                $q->whereNull('last_scan_at')
                    ->orWhere('last_scan_at', '<', $threshold);
            })
            ->with('user:id,name', 'department:id,name')
            ->orderByRaw('last_scan_at IS NULL DESC') // primero los que nunca reportaron
            ->orderBy('last_scan_at', 'asc');
    }
}
