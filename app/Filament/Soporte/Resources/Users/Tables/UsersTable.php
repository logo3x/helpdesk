<?php

namespace App\Filament\Soporte\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color('success'),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Nunca'),
            ])
            ->defaultSort('name')
            ->recordActions([
                Impersonate::make()
                    ->label('Impersonar')
                    ->visible(fn (User $record) => auth()->user()?->canImpersonateTarget($record)),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
