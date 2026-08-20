<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Solo un super_admin puede editar/borrar a otro super_admin. Los admins
     * tienen permiso general sobre users vía Shield, pero se les bloquea el
     * acceso puntual a registros con rol super_admin para prevenir
     * escalamiento de privilegios o quitarle el rol al usuario raíz.
     */
    public static function canEdit(Model $record): bool
    {
        if ($record instanceof User
            && $record->hasRole('super_admin')
            && ! auth()->user()?->hasRole('super_admin')) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof User
            && $record->hasRole('super_admin')
            && ! auth()->user()?->hasRole('super_admin')) {
            return false;
        }

        return parent::canDelete($record);
    }
}
