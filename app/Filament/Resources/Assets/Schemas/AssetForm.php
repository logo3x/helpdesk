<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\ManagementArea;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
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
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                TextInput::make('asset_tag')
                                    ->label('TAG / Etiqueta de inventario')
                                    ->placeholder('Ej: 11829')
                                    ->maxLength(50),

                                TextInput::make('sap_code')
                                    ->label('Código SAP')
                                    ->placeholder('Ej: OECC1528050500003236')
                                    ->maxLength(60)
                                    ->unique(table: 'assets', column: 'sap_code', ignorable: fn ($record) => $record),

                                TextInput::make('hostname')
                                    ->label('Hostname')
                                    ->placeholder('PC-RRHH-01')
                                    ->maxLength(255),

                                TextInput::make('serial_number')
                                    ->label('Serial / Service Tag')
                                    ->placeholder('9580WW3')
                                    ->maxLength(255)
                                    ->unique(table: 'assets', column: 'serial_number', ignorable: fn ($record) => $record),

                                Select::make('type')
                                    ->label('Tipo')
                                    ->options([
                                        'laptop' => 'Laptop',
                                        'all_in_one' => 'Todo en uno',
                                        'monitor' => 'Monitor',
                                        'server' => 'Servidor',
                                        'printer' => 'Impresora',
                                        'phone' => 'Teléfono / Celular',
                                        'tablet' => 'Tablet',
                                        'radio' => 'Radio',
                                        'antenna' => 'Antena',
                                        'network_kit' => 'Kit de Red',
                                        'ups' => 'UPS',
                                        'other' => 'Otro',
                                    ])
                                    ->default('laptop')
                                    ->required()
                                    ->native(false),

                                Select::make('status')
                                    ->label('Condición / Estado')
                                    ->options([
                                        'active' => 'Activo (bueno)',
                                        'fair' => 'Regular',
                                        'in_repair' => 'En reparación',
                                        'retired' => 'Retirado',
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Asignación')
                    ->icon('heroicon-o-user-circle')
                    ->description('Custodio del equipo, departamento y proyecto al que se carga.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Select::make('user_id')
                                    ->label('Custodio (usuario asignado)')
                                    ->relationship('user', 'name')
                                    ->searchable(['name', 'email', 'identification'])
                                    ->preload()
                                    ->placeholder('Sin asignar')
                                    ->helperText('Busca por nombre, correo o cédula.')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->custodianLabel())
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $user = User::find($state);
                                        if (! $user) {
                                            return;
                                        }
                                        $set('custodian_name', $user->name);
                                        $set('custodian_id_number', $user->identification ?? '');
                                        $set('custodian_position', $user->position ?? '');
                                        $set('department_id', $user->department_id);
                                        $set('management_area', $user->management_area ?? '');
                                        $set('field', $user->field ?? '');
                                        $set('location_zone', $user->location_zone ?? '');
                                    }),

                                Select::make('secondaryCustodians')
                                    ->label('Custodios secundarios')
                                    ->relationship('secondaryCustodians', 'name')
                                    ->multiple()
                                    ->searchable(['name', 'email', 'identification'])
                                    ->preload()
                                    ->placeholder('Sin custodios adicionales')
                                    ->helperText('Usuarios que también tienen acceso al activo en "Mis activos".')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->custodianLabel())
                                    ->columnSpanFull(),

                                TextInput::make('custodian_name')
                                    ->label('Nombre del custodio')
                                    ->placeholder('Ej: Juan Pérez (si no tiene cuenta en el sistema)')
                                    ->helperText('Nombre libre cuando el custodio no tiene cuenta.')
                                    ->maxLength(150),

                                TextInput::make('custodian_id_number')
                                    ->label('Cédula del custodio')
                                    ->placeholder('Ej: 12345678')
                                    ->maxLength(30),

                                TextInput::make('custodian_position')
                                    ->label('Cargo del custodio')
                                    ->placeholder('Ej: Técnico de campo')
                                    ->maxLength(150),

                                Select::make('department_id')
                                    ->label('Departamento')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Sin departamento'),

                                Select::make('project_id')
                                    ->label('Proyecto')
                                    ->relationship('project', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code} · {$record->name}")
                                    ->searchable(['code', 'name', 'client'])
                                    ->preload()
                                    ->placeholder('Sin proyecto')
                                    ->helperText('Busca por código (ej: 499015105) o nombre del proyecto.'),

                                Select::make('management_area')
                                    ->label('Gerencia')
                                    ->options(ManagementArea::options())
                                    ->native(false)
                                    ->searchable()
                                    ->placeholder('Seleccioná una gerencia'),

                                TextInput::make('field')
                                    ->label('Campo')
                                    ->placeholder('Ej: PORE, SAN MARTIN, CARUPANA')
                                    ->maxLength(100),

                                TextInput::make('location_zone')
                                    ->label('Ubicación')
                                    ->placeholder('Ej: Bodega central, Piso 3')
                                    ->maxLength(100),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Compra y garantía')
                    ->icon('heroicon-o-currency-dollar')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                DatePicker::make('purchased_at')
                                    ->label('Fecha de compra')
                                    ->displayFormat('d/m/Y')
                                    ->native(false),

                                TextInput::make('purchase_cost')
                                    ->label('Costo')
                                    ->numeric()
                                    ->prefix('$')
                                    ->minValue(0)
                                    ->step(0.01),

                                Select::make('purchase_currency')
                                    ->label('Moneda')
                                    ->options([
                                        'COP' => 'COP — Peso colombiano',
                                        'USD' => 'USD — Dólar',
                                        'EUR' => 'EUR — Euro',
                                    ])
                                    ->default('COP')
                                    ->native(false),

                                TextInput::make('purchase_order')
                                    ->label('Orden de compra')
                                    ->placeholder('Ej: OC-2024-1234')
                                    ->maxLength(80),

                                TextInput::make('supplier')
                                    ->label('Proveedor')
                                    ->placeholder('Ej: Compumax, Dell Colombia')
                                    ->maxLength(255),

                                DatePicker::make('warranty_expires_at')
                                    ->label('Vence garantía')
                                    ->displayFormat('d/m/Y')
                                    ->native(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Hardware')
                    ->icon('heroicon-o-cpu-chip')
                    ->collapsible()
                    ->collapsed()
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
                    ->collapsed()
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

                Section::make('Red y conectividad')
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

                                TextInput::make('phone_line')
                                    ->label('Línea telefónica')
                                    ->placeholder('Solo para celulares')
                                    ->maxLength(30),

                                TextInput::make('imei')
                                    ->label('IMEI')
                                    ->placeholder('Solo para celulares')
                                    ->maxLength(30),
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

                        TextInput::make('registration_source_label')
                            ->label('Origen del registro')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => match ($record?->registration_source) {
                                'scan_web' => 'ScanConfi (navegador web)',
                                'scan_agent' => 'ScanConfi (agente PowerShell)',
                                'manual' => 'Registro manual',
                                default => '—',
                            })
                            ->helperText('Indica cómo fue creado el registro inicial del activo.'),

                        TextInput::make('created_by_label')
                            ->label('Registrado por')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->createdBy?->name ?? '—'),

                        TextInput::make('accepted_at_label')
                            ->label('Aceptado por custodio')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->accepted_at
                                ? ($record->acceptedBy?->name ?? '—').' — '.$record->accepted_at->translatedFormat('d/m/Y H:i')
                                : 'No aceptado aún'
                            )
                            ->helperText('Fecha en que el custodio confirmó haber recibido el activo desde el portal.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
