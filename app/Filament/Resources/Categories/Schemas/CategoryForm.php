<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Select::make('parent_id')
                    ->label('Categoría padre (opcional)')
                    ->helperText('Si eliges una, esta queda como sub-categoría. Solo se admite un nivel de jerarquía.')
                    ->placeholder('— ninguna (categoría raíz) —')
                    ->options(function (Get $get, ?Category $record) {
                        $departmentId = $get('department_id');

                        if (! $departmentId) {
                            return [];
                        }

                        return Category::query()
                            ->where('department_id', $departmentId)
                            ->whereNull('parent_id')
                            ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->disabled(fn (?Category $record) => $record !== null && ! $record->canBecomeChild())
                    ->dehydrated()
                    ->searchable()
                    ->preload(),

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
                    ->maxLength(150)
                    ->unique(Category::class, 'slug', ignoreRecord: true),

                TextInput::make('icon')
                    ->label('Icono (Heroicon)')
                    ->placeholder('heroicon-o-computer-desktop')
                    ->maxLength(60)
                    ->helperText('Nombre del icono Heroicon opcional.'),

                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Orden')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }
}
