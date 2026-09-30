<?php

namespace App\Filament\Resources\SlaConfigs;

use App\Filament\Resources\SlaConfigs\Pages\CreateSlaConfig;
use App\Filament\Resources\SlaConfigs\Pages\EditSlaConfig;
use App\Filament\Resources\SlaConfigs\Pages\ListSlaConfigs;
use App\Filament\Resources\SlaConfigs\Schemas\SlaConfigForm;
use App\Filament\Resources\SlaConfigs\Tables\SlaConfigsTable;
use App\Models\SlaConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Políticas SLA por departamento × prioridad.
 *
 * Solo admin y super_admin. Cada cambio en los tiempos o en el estado
 * dispara la notificación masiva de SlaConfigObserver. Los cambios
 * aplican a los tickets nuevos; los existentes conservan sus fechas.
 */
class SlaConfigResource extends Resource
{
    protected static ?string $model = SlaConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Política SLA';

    protected static ?string $pluralModelLabel = 'Políticas SLA';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return SlaConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlaConfigsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlaConfigs::route('/'),
            'create' => CreateSlaConfig::route('/create'),
            'edit' => EditSlaConfig::route('/{record}/edit'),
        ];
    }
}
