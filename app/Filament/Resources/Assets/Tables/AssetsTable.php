<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
                    ->label('TAG')
                    ->searchable(),
                TextColumn::make('hostname')
                    ->label('Hostname')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('sap_code')
                    ->label('Código SAP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label('Custodio')
                    ->placeholder('Sin asignar')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Depto')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->formatStateUsing(fn ($record) => $record->project ? "{$record->project->code} · {$record->project->name}" : '—')
                    ->placeholder('—')
                    ->searchable(['projects.code', 'projects.name'])
                    ->toggleable(),
                TextColumn::make('field')
                    ->label('Campo')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('location_zone')
                    ->label('Ubicación')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        'fair' => 'info',
                        'in_repair' => 'warning',
                        'retired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Activo',
                        'fair' => 'Regular',
                        'in_repair' => 'En reparación',
                        'retired' => 'Retirado',
                        default => $state,
                    }),
                TextColumn::make('next_maintenance_at')
                    ->label('Próx. mantto.')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn ($record) => match ($record?->maintenance_status) {
                        'vencido' => 'danger',
                        'por vencer' => 'warning',
                        'vigente' => 'success',
                        default => 'gray',
                    })
                    ->description(fn ($record) => $record?->maintenance_status
                        ? '· '.ucfirst((string) $record->maintenance_status)
                        : null),
                TextColumn::make('last_scan_at')
                    ->label('Último scan')
                    ->dateTime('d M Y H:i')
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('agent_version')
                    ->label('Agente')
                    ->badge()
                    ->color(fn (?string $state) => match (true) {
                        $state === null => 'gray',
                        str_starts_with($state, '2.') => 'success',
                        default => 'warning',
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_scan_status')
                    ->label('Status scan')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'ok' => 'success',
                        'partial' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name'),
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} · {$record->name}")
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'desktop' => 'Desktop',
                        'laptop' => 'Laptop',
                        'all_in_one' => 'All-in-One',
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
                        'fair' => 'Regular',
                        'in_repair' => 'En reparación',
                        'retired' => 'Retirado',
                    ]),
                Filter::make('maintenance_due')
                    ->label('Mantenimiento próx. (≤30 días)')
                    ->query(fn (Builder $q) => $q->whereNotNull('next_maintenance_at')
                        ->where('next_maintenance_at', '<=', now()->addDays(30)))
                    ->toggle(),
                Filter::make('maintenance_overdue')
                    ->label('Mantenimiento vencido')
                    ->query(fn (Builder $q) => $q->whereNotNull('next_maintenance_at')
                        ->where('next_maintenance_at', '<', now()))
                    ->toggle(),
                Filter::make('stale_scan')
                    ->label('Sin scan en últimos 30 días')
                    ->query(fn (Builder $q) => $q->where(fn ($q) => $q
                        ->whereNull('last_scan_at')
                        ->orWhere('last_scan_at', '<', now()->subDays(30))
                    ))
                    ->toggle(),
                Filter::make('agent_outdated')
                    ->label('Agente desactualizado (no v2)')
                    ->query(fn (Builder $q) => $q->whereNotNull('last_scan_at')
                        ->where(fn ($qq) => $qq
                            ->whereNull('agent_version')
                            ->orWhere('agent_version', 'not like', '2.%')
                        ))
                    ->toggle(),
                Filter::make('scan_problems')
                    ->label('Scan con errores parciales')
                    ->query(fn (Builder $q) => $q->whereIn('last_scan_status', ['partial', 'error']))
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
                // ── Acciones rápidas por activo ────────────────────────
                // Cambiar custodio sin abrir el form de edit completo.
                Action::make('transferCustodian')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->modalHeading(fn (Asset $record) => "Transferir custodia de {$record->asset_tag}")
                    ->modalDescription('Cambia el responsable del activo sin generar acta. Para generar acta formal, usa el botón "Generar acta de entrega" desde la edición.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Nuevo custodio')
                            ->relationship('user', 'name')
                            ->searchable(['name', 'email', 'identification'])
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} · {$record->email}"),
                        Textarea::make('notes')
                            ->label('Nota (opcional)')
                            ->placeholder('Ej: "Préstamo por mantenimiento del equipo titular"')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        $newUser = User::findOrFail($data['user_id']);
                        $oldUserId = $record->user_id;

                        $record->forceFill([
                            'user_id' => $newUser->id,
                            'department_id' => $newUser->department_id ?? $record->department_id,
                        ])->save();

                        // Registro en histórico para que aparezca en la
                        // hoja de vida del activo.
                        $record->histories()->create([
                            'user_id' => auth()->id(),
                            'action' => 'assigned',
                            'field' => 'user_id',
                            'old_value' => (string) $oldUserId,
                            'new_value' => (string) $newUser->id,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Custodia transferida')
                            ->body("Nuevo custodio: {$newUser->name}")
                            ->success()
                            ->send();
                    }),

                // Marcar mantenimiento realizado: actualiza last_maintenance_at
                // y deja que el modelo recalcule next_maintenance_at solo.
                Action::make('markMaintenance')
                    ->label('Mtto realizado')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->modalHeading(fn (Asset $record) => "Registrar mantenimiento de {$record->asset_tag}")
                    ->modalDescription('Marca el activo como mantenido hoy. La próxima fecha se calcula automáticamente con el intervalo configurado.')
                    ->schema([
                        DatePicker::make('done_at')
                            ->label('Fecha del mantenimiento')
                            ->default(now())
                            ->required(),
                        TextInput::make('interval')
                            ->label('Intervalo (días)')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (Asset $record) => $record->maintenance_interval_days ?? 180)
                            ->helperText('Días hasta el próximo mantenimiento. Si lo dejás vacío, se usa el valor previo del activo.'),
                        Textarea::make('notes')
                            ->label('Observaciones (opcional)')
                            ->placeholder('Ej: "Limpieza interna + cambio de pasta térmica"')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        $record->forceFill([
                            'last_maintenance_at' => $data['done_at'],
                            'maintenance_interval_days' => $data['interval'] ?? $record->maintenance_interval_days,
                        ])->save(); // El booted() hook recalcula next_maintenance_at.

                        $record->histories()->create([
                            'user_id' => auth()->id(),
                            'action' => 'maintenance',
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Mantenimiento registrado')
                            ->body('Próximo: '.$record->fresh()->next_maintenance_at?->translatedFormat('d/m/Y'))
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->label('Exportar selección'),

                    // ── Bulk: transferir custodio de varios activos ────
                    BulkAction::make('bulkTransfer')
                        ->label('Transferir custodia')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('info')
                        ->modalHeading('Transferir varios activos al mismo custodio')
                        ->schema([
                            Select::make('user_id')
                                ->label('Nuevo custodio')
                                ->relationship('user', 'name')
                                ->searchable(['name', 'email', 'identification'])
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $newUser = User::findOrFail($data['user_id']);

                            foreach ($records as $asset) {
                                $oldUserId = $asset->user_id;
                                $asset->forceFill([
                                    'user_id' => $newUser->id,
                                    'department_id' => $newUser->department_id ?? $asset->department_id,
                                ])->save();

                                $asset->histories()->create([
                                    'user_id' => auth()->id(),
                                    'action' => 'assigned',
                                    'field' => 'user_id',
                                    'old_value' => (string) $oldUserId,
                                    'new_value' => (string) $newUser->id,
                                    'notes' => 'Bulk transfer',
                                ]);
                            }

                            Notification::make()
                                ->title(count($records).' activos transferidos a '.$newUser->name)
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // ── Bulk: marcar como retirado ─────────────────────
                    BulkAction::make('bulkRetire')
                        ->label('Marcar como retirado')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Dar de baja los activos seleccionados')
                        ->modalDescription('Los activos quedarán con status=retired. No se borran de la BD, solo dejan de aparecer en filtros por defecto.')
                        ->action(function (Collection $records): void {
                            foreach ($records as $asset) {
                                $asset->forceFill(['status' => 'retired'])->save();

                                $asset->histories()->create([
                                    'user_id' => auth()->id(),
                                    'action' => 'retired',
                                    'notes' => 'Bulk retire',
                                ]);
                            }

                            Notification::make()
                                ->title(count($records).' activos marcados como retirados')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('last_scan_at', 'desc');
    }
}
