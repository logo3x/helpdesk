<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
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
                            ->relationship('roles', 'name')
                            ->options(Role::pluck('name', 'name'))
                            ->required()
                            ->live()
                            ->multiple(false)
                            ->saveRelationshipsUsing(function ($component, $state, $record) {
                                if ($state) {
                                    $record->syncRoles([$state]);
                                }
                            })
                            ->dehydrated(true)
                            ->afterStateHydrated(function ($component, $state, $record) {
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
            ]);
    }
}
