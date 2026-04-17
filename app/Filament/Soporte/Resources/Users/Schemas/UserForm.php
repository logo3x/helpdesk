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
 * Supervisors can only create agente_soporte users for their own
 * department. The role and department fields are pre-filled and
 * disabled to enforce this rule.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nuevo agente del departamento')
                    ->description('Crea un agente de soporte para tu departamento. El rol y departamento se asignan automáticamente.')
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
                            ->helperText('Mínimo 8 caracteres. El agente la puede cambiar luego desde su perfil.'),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->relationship('department', 'name')
                            ->default(fn () => auth()->user()?->department_id)
                            ->disabled(fn () => ! auth()->user()?->hasAnyRole(['super_admin', 'admin']))
                            ->dehydrated()
                            ->required()
                            ->helperText('Los supervisores solo pueden crear agentes en su propio departamento.'),
                    ])
                    ->columns(2),
            ]);
    }
}
