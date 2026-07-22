<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
                // Equipo — TAG + hostname apilados, icono según tipo
                TextColumn::make('asset_tag')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable()
                    ->icon(fn ($record) => match ($record?->type) {
                        'laptop', 'notebook' => 'heroicon-o-computer-desktop',
                        'server' => 'heroicon-o-server',
                        'printer' => 'heroicon-o-printer',
                        'phone' => 'heroicon-o-device-phone-mobile',
                        'tablet' => 'heroicon-o-device-tablet',
                        default => 'heroicon-o-computer-desktop',
                    })
                    ->iconColor('primary')
                    ->description(fn ($record) => $record?->hostname ?? $record?->serial_number ?? '—')
                    ->placeholder('Sin TAG'),

                // Custodio — nombre del usuario + nombre libre apilados
                TextColumn::make('user.name')
                    ->label('Custodio')
                    ->icon('heroicon-o-user')
                    ->iconColor('gray')
                    ->placeholder('Sin asignar')
                    ->searchable()
                    ->description(fn ($record) => $record?->custodian_name
                        ? $record->custodian_name
                        : ($record?->department?->name ?? null)),

                // Tipo + Estado como badges en una sola celda
                TextColumn::make('type')
                    ->label('Tipo / Estado')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'laptop', 'notebook' => 'info',
                        'server' => 'warning',
                        'printer' => 'gray',
                        'phone' => 'success',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'desktop' => 'Desktop',
                        'laptop' => 'Laptop',
                        'notebook' => 'Notebook',
                        'all_in_one' => 'All-in-One',
                        'server' => 'Servidor',
                        'printer' => 'Impresora',
                        'phone' => 'Teléfono',
                        'tablet' => 'Tablet',
                        'other' => 'Otro',
                        default => $state ?? '—',
                    })
                    ->searchable()
                    ->description(fn ($record) => match ($record?->status) {
                        'active' => 'Activo',
                        'fair' => 'Regular',
                        'in_repair' => 'En reparación',
                        'retired' => 'Retirado',
                        default => null,
                    }),

                // Hardware: SO + RAM/Disco apilados
                TextColumn::make('os_name')
                    ->label('Hardware')
                    ->icon('heroicon-o-cpu-chip')
                    ->iconColor('gray')
                    ->formatStateUsing(fn ($state, $record) => collect([
                        $state,
                        $record?->os_version ? 'v'.$record->os_version : null,
                    ])->filter()->join(' '))
                    ->placeholder('—')
                    ->searchable()
                    ->description(fn ($record) => collect([
                        $record?->ram_mb ? round($record->ram_mb / 1024).' GB RAM' : null,
                        $record?->disk_total_gb ? $record->disk_total_gb.' GB Disco' : null,
                        $record?->cpu_cores ? $record->cpu_cores.' cores' : null,
                    ])->filter()->join(' · ') ?: null)
                    ->toggleable(),

                // Proyecto + Campo apilados
                TextColumn::make('project.name')
                    ->label('Proyecto / Campo')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('gray')
                    ->formatStateUsing(fn ($record) => $record?->project
                        ? "{$record->project->code} · {$record->project->name}"
                        : '—')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->orWhereHas('project', function (Builder $q) use ($search) {
                            $q->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                    })
                    ->description(fn ($record) => collect([
                        $record?->field,
                        $record?->location_zone,
                    ])->filter()->join(' · ') ?: null)
                    ->toggleable(),

                // IP + MAC
                TextColumn::make('ip_address')
                    ->label('Red')
                    ->icon('heroicon-o-signal')
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->description(fn ($record) => $record?->mac_address ?? null)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Estado de mantenimiento
                TextColumn::make('next_maintenance_at')
                    ->label('Mantenimiento')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->iconColor(fn ($record) => match ($record?->maintenance_status) {
                        'vencido' => 'danger',
                        'por vencer' => 'warning',
                        'vigente' => 'success',
                        default => 'gray',
                    })
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
                        ? ucfirst((string) $record->maintenance_status)
                        : null),

                // Último scan + versión agente
                TextColumn::make('last_scan_at')
                    ->label('Último scan')
                    ->icon(fn ($record) => match ($record?->last_scan_status) {
                        'ok', 'agent_scan', 'web_scan' => 'heroicon-o-check-circle',
                        'partial' => 'heroicon-o-exclamation-circle',
                        'error' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-clock',
                    })
                    ->iconColor(fn ($record) => match ($record?->last_scan_status) {
                        'ok', 'agent_scan', 'web_scan' => 'success',
                        'partial' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    })
                    ->since()
                    ->placeholder('Nunca')
                    ->sortable()
                    ->description(fn ($record) => $record?->agent_version ?? null),

                // Columnas ocultas por defecto (accesibles via toggle)
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sap_code')
                    ->label('Código SAP')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cpu_model')
                    ->label('CPU')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('mac_address')
                    ->label('MAC')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Aceptación por custodio
                TextColumn::make('accepted_at')
                    ->label('Aceptado')
                    ->icon(fn ($record) => $record?->accepted_at ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                    ->iconColor(fn ($record) => $record?->accepted_at ? 'success' : 'gray')
                    ->date('d/m/Y')
                    ->placeholder('No aceptado')
                    ->sortable()
                    ->description(fn ($record) => $record?->acceptedBy?->name ?? null)
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
                Filter::make('accepted')
                    ->label('Aceptados por custodio')
                    ->query(fn (Builder $q) => $q->whereNotNull('accepted_at'))
                    ->toggle(),
                Filter::make('not_accepted')
                    ->label('Sin aceptar por custodio')
                    ->query(fn (Builder $q) => $q->whereNull('accepted_at'))
                    ->toggle(),
                TrashedFilter::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar a Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->tooltip('Descargar el inventario filtrado a un archivo .xlsx')
                    ->exports([
                        ExcelExport::make('xlsx')
                            ->fromTable()
                            ->withFilename(fn () => 'inventario-'.now()->format('Y-m-d').'.xlsx')
                            ->askForWriterType(),
                    ]),
            ])
            ->recordActions([
                // ── Acción primaria: Editar — siempre visible ──────────
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar activo')
                    ->color('primary'),

                // ── Acción primaria: Hoja de vida — siempre visible ────
                Action::make('viewLifecycle')
                    ->iconButton()
                    ->tooltip('Ver hoja de vida (timeline completo)')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->url(fn (Asset $record): string => url("/assets/{$record->id}/edit/pdf"))
                    ->openUrlInNewTab(),

                // ── Acciones secundarias en menú ⋮ ─────────────────────
                ActionGroup::make([
                    // Cambiar custodio sin abrir el form de edit completo.
                    Action::make('transferCustodian')
                        ->label('Transferir custodia')
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
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->custodianLabel()),
                            Textarea::make('notes')
                                ->label('Nota (opcional)')
                                ->placeholder('Ej: "Préstamo por mantenimiento del equipo titular"')
                                ->rows(2)
                                ->maxLength(500),
                        ])
                        ->action(function (Asset $record, array $data): void {
                            $newUser = User::findOrFail($data['user_id']);
                            $oldUserId = $record->user_id;

                            $record->skipAutoHistory = true;
                            $record->forceFill([
                                'user_id' => $newUser->id,
                                'custodian_name' => $newUser->name,
                                'department_id' => $newUser->department_id ?? $record->department_id,
                            ])->save();

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
                        ->label('Registrar mantenimiento')
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
                            // Skip auto-history: el evento "maintenance" se
                            // crea con su label propio, no como "updated".
                            $interval = $data['interval'] ?? $record->maintenance_interval_days;
                            $record->skipAutoHistory = true;
                            $record->forceFill([
                                'last_maintenance_at' => $data['done_at'],
                                'maintenance_interval_days' => $interval,
                            ])->save(); // El booted() hook recalcula next_maintenance_at.

                            $parts = array_filter([
                                $interval ? "Frecuencia: {$interval} días" : null,
                                $data['notes'] ?? null,
                            ]);

                            $record->histories()->create([
                                'user_id' => auth()->id(),
                                'action' => 'maintenance',
                                'field' => 'last_maintenance_at',
                                'new_value' => $data['done_at'],
                                'notes' => implode(' | ', $parts) ?: null,
                            ]);

                            Notification::make()
                                ->title('Mantenimiento registrado')
                                ->body('Próximo: '.$record->fresh()->next_maintenance_at?->translatedFormat('d/m/Y'))
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Más acciones')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->size('sm')
                    ->tooltip('Más acciones')
                    ->button(),
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
                                ->required()
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->custodianLabel()),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $newUser = User::findOrFail($data['user_id']);

                            foreach ($records as $asset) {
                                $oldUserId = $asset->user_id;
                                $asset->skipAutoHistory = true;
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
