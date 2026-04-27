<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form de Asset (inventario) organizado en secciones para escaneo
 * rápido. Pensado para que IT identifique un equipo, vea sus specs y
 * actualice asignación o estado en pocos clicks.
 *
 *   1. Identificación (etiqueta, hostname, serial, tipo)
 *   2. Asignación (usuario, depto, estado) — la más editada
 *   3. Hardware (CPU, RAM, disco, GPU)
 *   4. Sistema operativo
 *   5. Red (IP, MAC)
 *   6. Notas + último scan
 */
class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identificación')
                    ->icon('heroicon-o-identification')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('asset_tag')
                                    ->label('Etiqueta de inventario')
                                    ->placeholder('Ej: CONF-LAP-0042')
                                    ->maxLength(50),

                                TextInput::make('hostname')
                                    ->label('Hostname')
                                    ->placeholder('PC-RRHH-01')
                                    ->maxLength(255),

                                TextInput::make('serial_number')
                                    ->label('Serial / Service Tag')
                                    ->maxLength(255),

                                Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'desktop' => 'Desktop',
                                        'laptop' => 'Laptop',
                                        'server' => 'Servidor',
                                        'printer' => 'Impresora',
                                        'phone' => 'Teléfono',
                                        'tablet' => 'Tablet',
                                        'other' => 'Otro',
                                    ])
                                    ->default('desktop')
                                    ->required()
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Asignación')
                    ->icon('heroicon-o-user-circle')
                    ->description('A quién pertenece el equipo y en qué estado se encuentra. Es lo que más se actualiza desde aquí.')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('user_id')
                                    ->label('Usuario asignado')
                                    ->relationship('user', 'name')
                                    ->searchable(['name', 'email'])
                                    ->preload()
                                    ->placeholder('Sin asignar')
                                    ->helperText('Busca por nombre o correo.')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} · {$record->email}"),

                                Select::make('department_id')
                                    ->label('Departamento')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Sin departamento'),

                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'active' => 'Activo',
                                        'in_repair' => 'En reparación',
                                        'retired' => 'Retirado',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Hardware')
                    ->icon('heroicon-o-cpu-chip')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('manufacturer')
                                    ->label('Fabricante')
                                    ->placeholder('Dell, HP, Lenovo...')
                                    ->maxLength(255),

                                TextInput::make('model')
                                    ->label('Modelo')
                                    ->placeholder('Latitude 5520, ProBook 450...')
                                    ->maxLength(255),

                                TextInput::make('cpu_model')
                                    ->label('CPU')
                                    ->placeholder('Intel Core i7-1165G7')
                                    ->maxLength(255),

                                TextInput::make('cpu_cores')
                                    ->label('Núcleos')
                                    ->numeric()
                                    ->minValue(1),

                                TextInput::make('ram_mb')
                                    ->label('RAM (MB)')
                                    ->numeric()
                                    ->suffix('MB')
                                    ->helperText('8192 = 8 GB · 16384 = 16 GB · 32768 = 32 GB'),

                                TextInput::make('disk_total_gb')
                                    ->label('Disco total')
                                    ->numeric()
                                    ->suffix('GB'),

                                TextInput::make('gpu_info')
                                    ->label('GPU')
                                    ->placeholder('Intel UHD Graphics 620')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Sistema operativo')
                    ->icon('heroicon-o-window')
                    ->collapsible()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('os_name')
                                    ->label('SO')
                                    ->placeholder('Windows 11')
                                    ->maxLength(255),

                                TextInput::make('os_version')
                                    ->label('Versión')
                                    ->placeholder('22H2')
                                    ->maxLength(255),

                                TextInput::make('os_architecture')
                                    ->label('Arquitectura')
                                    ->placeholder('64-bit')
                                    ->maxLength(20),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Red')
                    ->icon('heroicon-o-globe-alt')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('ip_address')
                                    ->label('IP')
                                    ->placeholder('192.168.1.42')
                                    ->maxLength(45),

                                TextInput::make('mac_address')
                                    ->label('MAC')
                                    ->placeholder('AA:BB:CC:DD:EE:FF')
                                    ->maxLength(17),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Notas y último scan')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->placeholder('Observaciones, modificaciones o detalles relevantes del equipo.')
                            ->columnSpanFull(),

                        DateTimePicker::make('last_scan_at')
                            ->label('Último scan automático')
                            ->disabled()
                            ->helperText('Se actualiza automáticamente cuando el agente PowerShell o el web-scan reportan.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
