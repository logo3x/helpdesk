<?php

namespace App\Filament\Resources\Assets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_tag')
                    ->label('Etiqueta')
                    ->searchable(),
                TextColumn::make('hostname')
                    ->label('Hostname')
                    ->searchable(),
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->searchable(),
                TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sin asignar')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Depto')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('os_name')
                    ->label('SO')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('os_version')
                    ->label('Versión SO')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpu_cores')
                    ->label('Cores')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpu_model')
                    ->label('CPU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ram_mb')
                    ->label('RAM (MB)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('disk_total_gb')
                    ->label('Disco (GB)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mac_address')
                    ->label('MAC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'in_repair' => 'warning',
                        'retired' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('last_scan_at')
                    ->label('Último scan')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name'),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'desktop' => 'Desktop',
                        'laptop' => 'Laptop',
                        'server' => 'Servidor',
                        'printer' => 'Impresora',
                        'phone' => 'Teléfono',
                        'tablet' => 'Tablet',
                        'other' => 'Otro',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'in_repair' => 'En reparación',
                        'retired' => 'Retirado',
                    ]),
                Filter::make('stale_scan')
                    ->label('Sin scan en últimos 30 días')
                    ->query(fn (Builder $q) => $q->where(fn ($q) => $q
                        ->whereNull('last_scan_at')
                        ->orWhere('last_scan_at', '<', now()->subDays(30))
                    ))
                    ->toggle(),
                TrashedFilter::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar inventario')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->exports([
                        ExcelExport::make('xlsx')
                            ->fromTable()
                            ->withFilename(fn () => 'inventario-'.now()->format('Y-m-d').'.xlsx')
                            ->askForWriterType(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->label('Exportar selección'),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_scan_at', 'desc');
    }
}
