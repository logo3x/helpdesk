<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, TextInput $component) {
                        if ($component->getRecord() !== null) {
                            return;
                        }

                        $component
                            ->getContainer()
                            ->getComponent(fn ($c) => $c instanceof TextInput && $c->getName() === 'slug')
                            ?->state(Str::slug((string) $state));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(Department::class, 'slug', ignoreRecord: true)
                    ->helperText('Identificador en URLs. Se autogenera desde el nombre.'),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),

                Select::make('parent_id')
                    ->label('Departamento padre')
                    ->relationship(
                        'parent',
                        'name',
                        modifyQueryUsing: fn ($query, ?Department $record) => $record
                            ? $query->whereKeyNot($record->id)
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Sin padre (raíz)'),

                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),

                Toggle::make('can_access_inventory')
                    ->label('Acceso al módulo de Inventario')
                    ->helperText('Si está activo, los usuarios de este depto podrán ver el inventario de equipos en /soporte. Útil para el área de TI.')
                    ->default(false),
            ]);
    }
}
