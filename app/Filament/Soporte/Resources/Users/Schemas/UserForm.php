<?php

namespace App\Filament\Soporte\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Form for the Support panel's Users resource.
 *
 * Supervisors can create agente_soporte or usuario_final users.
 * Department is forced to their own for supervisors.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Contraseña inicial')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation) => $operation === 'create')
                            ->minLength(8)
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText('Mínimo 8 caracteres. El usuario la puede cambiar luego desde su perfil.'),

                        Select::make('role')
                            ->label('Rol')
                            ->options(fn () => auth()->user()?->hasAnyRole(['super_admin', 'admin'])
                                ? [
                                    'usuario_final' => 'Usuario final (portal /soporte)',
                                    'agente_soporte' => 'Agente de soporte',
                                ]
                                : ['usuario_final' => 'Usuario final (portal /soporte)']
                            )
                            ->default('usuario_final')
                            ->required()
                            ->dehydrated(false)
                            ->helperText('Usuario final: accede al portal para abrir tickets. Agente: gestiona tickets en el panel soporte.'),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->relationship('department', 'name')
                            ->default(fn () => auth()->user()?->department_id)
                            ->disabled(fn () => ! auth()->user()?->hasAnyRole(['super_admin', 'admin']))
                            ->dehydrated()
                            ->required()
                            ->helperText('Los supervisores solo pueden crear usuarios en su propio departamento.'),
                    ])
                    ->columns(2),
            ]);
    }
}
