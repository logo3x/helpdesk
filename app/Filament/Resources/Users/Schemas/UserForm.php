<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->helperText('En edición, dejar vacío para mantener la contraseña actual.'),
                    ])
                    ->columns(2),

                Section::make('Rol y departamento')
                    ->schema([
                        Select::make('roles')
                            ->label('Rol')
                            ->options(Role::pluck('name', 'name'))
                            ->required()
                            ->live()
                            // dehydrated:false porque NO queremos que Filament
                            // intente persistir 'roles' como columna del User.
                            // El sync real se hace en afterSave del page.
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record) {
                                    $component->state($record->roles->first()?->name);
                                }
                            })
                            ->helperText('Define qué panel puede acceder el usuario.'),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => in_array($get('roles'), ['supervisor_soporte', 'agente_soporte', 'tecnico_campo']))
                            ->visible(fn (Get $get) => in_array($get('roles'), ['supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'usuario_final', 'editor_kb']))
                            ->helperText('Obligatorio para agentes y supervisores: define qué tickets pueden ver.'),
                    ])
                    ->columns(2),

                Section::make('Integración Kactus')
                    ->description('Datos sincronizados desde el sistema de nómina. Solo lectura — se actualizan automáticamente.')
                    ->icon('heroicon-o-arrow-path')
                    ->collapsed()
                    ->visible(fn ($record) => $record && $record->kactus_employee_id)
                    ->schema([
                        TextInput::make('kactus_employee_id')
                            ->label('ID en Kactus')
                            ->disabled(),
                        TextInput::make('employment_status')
                            ->label('Estado laboral')
                            ->disabled()
                            ->formatStateUsing(fn (?string $state) => match ($state) {
                                'active' => 'Activo',
                                'terminated' => 'Retirado',
                                'on_leave' => 'En licencia',
                                default => $state ?? '—',
                            }),
                        Placeholder::make('hired_at')
                            ->label('Fecha de ingreso')
                            ->content(fn ($record) => $record?->hired_at?->translatedFormat('d M Y') ?? '—'),
                        Placeholder::make('terminated_at')
                            ->label('Fecha de retiro')
                            ->content(fn ($record) => $record?->terminated_at?->translatedFormat('d M Y') ?? '—'),
                        Placeholder::make('kactus_synced_at')
                            ->label('Última sincronización')
                            ->content(fn ($record) => $record?->kactus_synced_at?->diffForHumans() ?? 'Nunca'),
                    ])
                    ->columns(2),
            ]);
    }
}
