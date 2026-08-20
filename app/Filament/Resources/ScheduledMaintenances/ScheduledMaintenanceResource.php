<?php

namespace App\Filament\Resources\ScheduledMaintenances;

use App\Filament\Resources\ScheduledMaintenances\Pages\CreateScheduledMaintenance;
use App\Filament\Resources\ScheduledMaintenances\Pages\EditScheduledMaintenance;
use App\Filament\Resources\ScheduledMaintenances\Pages\ListScheduledMaintenances;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Schemas\ScheduledMaintenanceForm;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Tables\ScheduledMaintenancesTable;
use App\Models\ScheduledMaintenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Mantenimientos programados desde el panel /admin.
 *
 * Reusa Form/Table del panel Soporte (no duplica código); solo cambia
 * el URL binding para que las páginas resuelvan bajo /admin/*.
 * Acceso restringido a super_admin y admin (planificación gerencial).
 */
class ScheduledMaintenanceResource extends Resource
{
    protected static ?string $model = ScheduledMaintenance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Mantenimiento';

    protected static ?string $pluralModelLabel = 'Mantenimientos programados';

    protected static ?string $navigationLabel = 'Mantenimientos';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return ScheduledMaintenanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduledMaintenancesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduledMaintenances::route('/'),
            'create' => CreateScheduledMaintenance::route('/create'),
            'edit' => EditScheduledMaintenance::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
