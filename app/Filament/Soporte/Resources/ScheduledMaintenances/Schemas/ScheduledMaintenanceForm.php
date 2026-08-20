<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances\Schemas;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Formulario del módulo Mantenimientos Programados.
 *
 * Reglas UI:
 *   - Al crear: campos programación (asset, agente, fecha, frecuencia).
 *   - Al editar: además status, avance, observations y (si status
 *     no_cumplido) motivo. Si el agente edita, observations es
 *     obligatoria.
 *   - Solo activos tipo desktop/laptop/all_in_one/server aparecen en
 *     el select de asset.
 */
class ScheduledMaintenanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Programación')
                    ->icon('heroicon-o-calendar')
                    ->columns(2)
                    ->schema([
                        Select::make('asset_id')
                            ->label('Activo')
                            ->relationship(
                                name: 'asset',
                                titleAttribute: 'asset_tag',
                                modifyQueryUsing: fn ($query) => $query
                                    ->whereIn('type', ['desktop', 'laptop', 'all_in_one', 'server'])
                                    ->orderBy('asset_tag'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Asset $r) => trim(sprintf(
                                '%s · %s %s',
                                $r->asset_tag ?: 'sin TAG',
                                strtoupper($r->type ?? ''),
                                $r->hostname ? "· {$r->hostname}" : ''
                            )))
                            ->searchable(['asset_tag', 'hostname', 'serial_number'])
                            ->preload()
                            ->required()
                            ->disabled(fn (string $operation) => $operation === 'edit')
                            ->dehydrated()
                            ->helperText('Solo se pueden programar mantenimientos a computadores, portátiles, all-in-one y servidores.'),

                        Select::make('agent_id')
                            ->label('Agente asignado')
                            ->relationship(
                                name: 'agent',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->whereHas('roles', fn ($q) => $q->whereIn('name', [
                                    'super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo',
                                ]))->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (User $r) => $r->name.($r->email ? " · {$r->email}" : ''))
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->helperText('Recibirá una notificación en la campanita.'),

                        DatePicker::make('scheduled_at')
                            ->label('Fecha del mantenimiento')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (string $operation) => $operation === 'create' ? now()->startOfDay() : null),

                        Select::make('frequency')
                            ->label('Frecuencia')
                            ->options([
                                MaintenanceFrequency::Cuatrimestral->value => MaintenanceFrequency::Cuatrimestral->label(),
                                MaintenanceFrequency::Anual->value => MaintenanceFrequency::Anual->label(),
                            ])
                            ->required()
                            ->native(false)
                            ->helperText('Al marcar cumplido/no cumplido, se agenda automáticamente el siguiente ciclo.'),
                    ]),

                Section::make('Estado y ejecución')
                    ->icon('heroicon-o-check-circle')
                    ->columns(2)
                    ->visible(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        ToggleButtons::make('status')
                            ->label('Estado')
                            ->options([
                                MaintenanceStatus::Pendiente->value => MaintenanceStatus::Pendiente->label(),
                                MaintenanceStatus::Cumplido->value => MaintenanceStatus::Cumplido->label(),
                                MaintenanceStatus::NoCumplido->value => MaintenanceStatus::NoCumplido->label(),
                            ])
                            ->colors([
                                MaintenanceStatus::Pendiente->value => 'gray',
                                MaintenanceStatus::Cumplido->value => 'success',
                                MaintenanceStatus::NoCumplido->value => 'danger',
                            ])
                            ->icons([
                                MaintenanceStatus::Pendiente->value => 'heroicon-o-clock',
                                MaintenanceStatus::Cumplido->value => 'heroicon-o-check-badge',
                                MaintenanceStatus::NoCumplido->value => 'heroicon-o-x-circle',
                            ])
                            ->inline()
                            ->required()
                            ->live(),

                        Select::make('progress_percent')
                            ->label('Porcentaje de avance')
                            ->options(collect(range(0, 100, 10))
                                ->mapWithKeys(fn ($v) => [$v => "{$v}%"])
                                ->all())
                            ->default(0)
                            ->required()
                            ->native(false),

                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(4)
                            ->maxLength(2000)
                            ->required(fn (Get $get, string $operation) => $operation === 'edit'
                                && auth()->user()?->hasAnyRole(['agente_soporte', 'tecnico_campo']))
                            ->helperText('Obligatorio al editar un mantenimiento asignado. Registra qué se hizo, componentes revisados, hallazgos.')
                            ->columnSpanFull(),

                        Textarea::make('not_completed_reason')
                            ->label('Motivo de no cumplimiento')
                            ->rows(3)
                            ->maxLength(1000)
                            ->visible(fn (Get $get) => $get('status') === MaintenanceStatus::NoCumplido->value)
                            ->required(fn (Get $get) => $get('status') === MaintenanceStatus::NoCumplido->value)
                            ->helperText('Ej: "Usuario no disponible", "Repuesto pendiente", "Equipo en préstamo".')
                            ->columnSpanFull(),
                    ]),

                Section::make('Trazabilidad')
                    ->icon('heroicon-o-clock')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (string $operation) => $operation === 'edit')
                    ->schema([
                        Placeholder::make('created_by_display')
                            ->label('Programado por')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->createdBy?->name ?? '—'),

                        Placeholder::make('completed_at_display')
                            ->label('Cerrado el')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->completed_at?->translatedFormat('d M Y H:i') ?? 'Aún no cerrado'),

                        Placeholder::make('parent_display')
                            ->label('Ciclo anterior')
                            ->content(fn (?ScheduledMaintenance $record) => $record?->parent
                                ? '#'.$record->parent->id.' ('.$record->parent->scheduled_at->translatedFormat('d M Y').')'
                                : 'Este es el primer ciclo'),
                    ]),
            ]);
    }
}
