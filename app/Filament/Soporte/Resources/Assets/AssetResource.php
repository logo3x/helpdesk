<?php

namespace App\Filament\Soporte\Resources\Assets;

use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Filament\Soporte\Resources\Assets\Pages\AssetLifecycle;
use App\Filament\Soporte\Resources\Assets\Pages\CreateAsset;
use App\Filament\Soporte\Resources\Assets\Pages\EditAsset;
use App\Filament\Soporte\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * AssetResource para el panel /soporte.
 *
 * Solo es accesible para usuarios cuyo departamento tenga el flag
 * `can_access_inventory = true` (configurado por admin en Configuración
 * → Departamentos). super_admin / admin lo ven siempre.
 *
 * Reutiliza el form y la tabla del AssetResource de /admin para no
 * duplicar lógica.
 */
class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $modelLabel = 'Activo';

    protected static ?string $pluralModelLabel = 'Inventario';

    protected static ?string $recordTitleAttribute = 'hostname';

    protected static ?int $navigationSort = 40;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        // El depto del user debe tener can_access_inventory = true.
        return (bool) ($user->department?->can_access_inventory);
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'edit' => EditAsset::route('/{record}/edit'),
            'lifecycle' => AssetLifecycle::route('/{record}/lifecycle'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
