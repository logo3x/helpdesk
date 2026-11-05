<?php

namespace App\Filament\Soporte\Resources\Users\Tables;

use App\Filament\Resources\Users\Tables\UsersTable as AdminUsersTable;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Mismas columnas que /admin (cédula, rol, Kactus, ASL...) para
            // que los dos paneles muestren los mismos datos y tooltips.
            ->columns(AdminUsersTable::columns())
            ->filters([
                Filter::make('asl_pending')
                    ->label('Sin aceptar ASL')
                    ->query(fn (Builder $query): Builder => $query->whereNull('asl_accepted_at'))
                    ->toggle(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Impersonate::make()
                    ->label('Impersonar')
                    ->visible(fn (User $record) => auth()->user()?->canImpersonateTarget($record))
                    // Un supervisor solo impersona agentes → panel /soporte.
                    ->redirectTo('/soporte'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
