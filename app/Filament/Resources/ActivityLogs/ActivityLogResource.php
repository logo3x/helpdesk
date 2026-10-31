<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Bitácora de auditoría: inicios de sesión de todos los usuarios y lo que
 * hace cada persona en el sistema (crear, editar, eliminar registros).
 *
 * Solo lectura. La ven admin, super_admin y supervisor_soporte
 * (coordinador); se registra en los paneles admin y soporte.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $modelLabel = 'Registro de actividad';

    protected static ?string $pluralModelLabel = 'Bitácora de actividad';

    protected static ?string $navigationLabel = 'Bitácora';

    protected static ?int $navigationSort = 90;

    /** @var array<string, string> */
    public const EVENTS = [
        'login' => 'Inició sesión',
        'logout' => 'Cerró sesión',
        'failed' => 'Acceso fallido',
        'created' => 'Creó',
        'updated' => 'Editó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
    ];

    /** @var array<string, string> */
    public const MODULES = [
        'acceso' => 'Accesos',
        'tickets' => 'Tickets',
        'inventario' => 'Inventario',
        'mantenimientos' => 'Mantenimientos',
        'usuarios' => 'Usuarios',
        'sla' => 'Políticas SLA',
        'configuracion' => 'Configuración',
        'base_conocimiento' => 'Base de conocimiento',
    ];

    /** @var array<string, string> */
    public const SUBJECT_TYPES = [
        'App\Models\Ticket' => 'Ticket',
        'App\Models\TicketComment' => 'Comentario de ticket',
        'App\Models\Asset' => 'Activo',
        'App\Models\ScheduledMaintenance' => 'Mantenimiento',
        'App\Models\User' => 'Usuario',
        'App\Models\SlaConfig' => 'Política SLA',
        'App\Models\Category' => 'Categoría',
        'App\Models\Department' => 'Departamento',
        'App\Models\KbArticle' => 'Artículo KB',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'supervisor_soporte']) ?? false;
    }

    /**
     * Admin y super_admin ven todo. El coordinador (supervisor_soporte)
     * solo ve la actividad de los usuarios de su propio departamento.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->hasAnyRole(['super_admin', 'admin'])) {
            return $query;
        }

        return $query->whereHasMorph('causer', [User::class], fn (Builder $causer) => $causer
            ->whereNotNull('department_id')
            ->where('department_id', $user?->department_id));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }
}
