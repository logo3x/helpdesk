<?php

namespace App\Filament\Soporte\Resources\ScheduledMaintenances;

use App\Filament\Soporte\Resources\ScheduledMaintenances\Pages\CreateScheduledMaintenance;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Pages\EditScheduledMaintenance;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Pages\ListScheduledMaintenances;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Schemas\ScheduledMaintenanceForm;
use App\Filament\Soporte\Resources\ScheduledMaintenances\Tables\ScheduledMaintenancesTable;
use App\Models\ScheduledMaintenance;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Módulo Mantenimientos Programados — panel /soporte.
 *
 * Reglas de acceso:
 *   - super_admin/admin/supervisor_soporte: ver, crear, editar y borrar.
 *   - agente_soporte/tecnico_campo: solo pueden VER Y EDITAR los
 *     mantenimientos donde son el agente asignado. No pueden crear ni
 *     borrar.
 *   - Todos requieren que su departamento tenga can_access_inventory=true.
 */
class ScheduledMaintenanceResource extends Resource
{
    protected static ?string $model = ScheduledMaintenance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Mantenimiento';

    protected static ?string $pluralModelLabel = 'Mantenimientos programados';

    protected static ?string $navigationLabel = 'Mantenimientos';

    protected static ?int $navigationSort = 45;

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

        if (! $user->department?->can_access_inventory) {
            return false;
        }

        return $user->hasAnyRole(['supervisor_soporte', 'agente_soporte', 'tecnico_campo']);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::userCanWrite();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (static::userCanWrite()) {
            return true;
        }

        // Agente/técnico: solo su propio mantenimiento.
        return $user->hasAnyRole(['agente_soporte', 'tecnico_campo'])
            && $record instanceof ScheduledMaintenance
            && $record->agent_id === $user->id;
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanWrite();
    }

    protected static function userCanWrite(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return true;
        }

        return $user->hasRole('supervisor_soporte')
            && (bool) $user->department?->can_access_inventory;
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

    /**
     * Los agentes/técnicos solo ven los mantenimientos que se les
     * asignaron. Los supervisores ven los de su departamento
     * (comparando con el asset.department_id). Super_admin y admin
     * ven todos.
     */
    public static function getEloquentQuery(): Builder
    {
        // NO incluimos SoftDeletingScope::class en withoutGlobalScopes,
        // así los registros soft-deleted DESAPARECEN de la lista una
        // vez borrados. Filament sigue permitiendo verlos con la
        // acción "Restaurar" si activamos el trashed filter.
        $query = parent::getEloquentQuery();

        $user = auth()->user();
        if (! $user) {
            return $query;
        }

        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        if ($user->hasAnyRole(['agente_soporte', 'tecnico_campo'])) {
            return $query->where('agent_id', $user->id);
        }

        // Supervisor: ve mantenimientos de assets de su depto, más los
        // que él haya asignado o le hayan asignado, para no perder
        // visibilidad de casos cross-departamento (ej: creó mtto para
        // un activo prestado a otro depto).
        if ($user->hasRole('supervisor_soporte')) {
            return $query->where(function ($q) use ($user) {
                $q->where('agent_id', $user->id)
                    ->orWhere('created_by_id', $user->id);
                if ($user->department_id) {
                    $q->orWhereHas('asset', fn ($aq) => $aq->where('department_id', $user->department_id));
                }
            });
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
