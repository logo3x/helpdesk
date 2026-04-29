<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Models\Project;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Código del proyecto')
                    ->placeholder('Ej: 499015105')
                    ->required()
                    ->maxLength(30)
                    ->unique(Project::class, 'code', ignoreRecord: true)
                    ->helperText('Identificador numérico único (típicamente del ERP / SAP).'),

                TextInput::make('name')
                    ->label('Nombre del proyecto')
                    ->placeholder('Ej: PERENCO CARUPANA')
                    ->required()
                    ->maxLength(255),

                TextInput::make('client')
                    ->label('Cliente')
                    ->placeholder('Ej: Perenco, Grantierra')
                    ->maxLength(255)
                    ->helperText('Cliente final del contrato. Opcional.'),

                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->helperText('Solo proyectos activos aparecen en los selects al editar inventario.'),
            ]);
    }
}
